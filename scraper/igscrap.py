import os
import re
import time
from calendar import monthrange
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.parse import urlsplit

import requests
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright

load_dotenv(dotenv_path=Path(__file__).with_name(".env"))

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000/api/scraped-events")
API_TOKEN = os.getenv("SCRAPER_API_TOKEN")
API_TIMEOUT_SECONDS = 120

TARGET_ACCOUNTS = [
    "infobazaar",
    "pestapora",
    "synchronizefest",
    "jktgo",
    "eventjakarta",
    "kulinerdepok",
    "infotenantyk",
    "info.tenant",
]

TRIGGER_KEYWORDS = [
    "tenant",
    "sewa",
    "disewakan",
    "event space",
    "venue",
    "rental",
    "booking",
    "open house",
]

TIMELINE_MONTHS = 3
PROFILE_SCROLL_PAUSE_MS = 1500
MAX_STALLED_SCROLLS = 3


class NoPublicPostsError(RuntimeError):
    """Instagram tidak menampilkan permalink yang dapat diakses untuk profil."""


def subtract_months(value, months):
    """Kurangi bulan kalender dan sesuaikan tanggal jika bulan tujuan lebih pendek."""
    month_index = value.month - 1 - months
    year = value.year + month_index // 12
    month = month_index % 12 + 1
    day = min(value.day, monthrange(year, month)[1])
    return value.replace(year=year, month=month, day=day)


def contains_keywords(text):
    """Mengecek apakah caption mengandung salah satu kata kunci pemicu."""
    text_lower = text.lower()
    return any(keyword in text_lower for keyword in TRIGGER_KEYWORDS)


def parse_price(caption):
    """Mencoba mengekstrak angka harga sewa."""
    match = re.search(r"(?:rp|idr)\s*([\d.,]+)", caption.lower())
    if match:
        clean_price = re.sub(r"[^\d]", "", match.group(1))
        return int(clean_price) if clean_price else None
    return None


def parse_deadline(caption, now=None):
    """Parse clear relative closing phrases and return an ISO-8601 UTC deadline."""
    now = now or datetime.now(timezone.utc)
    patterns = (
        r"\bh\s*-\s*(\d+)\s*(?:hari\s*)?(?:lagi\s*)?(?:closing|close|deadline|tutup)\b",
        r"\b(?:closing|close|deadline|tutup)\s*(?:dalam\s*)?(\d+)\s*hari\b",
        r"\b(\d+)\s*hari\s*lagi\s*(?:closing|close|deadline|tutup)\b",
    )
    for pattern in patterns:
        match = re.search(pattern, caption, re.IGNORECASE)
        if match:
            return (now + timedelta(days=int(match.group(1)))).isoformat()

    return None


def post_shortcode(post_url):
    segments = urlsplit(post_url).path.strip("/").split("/")
    return segments[-1] if len(segments) >= 2 else None


def api_headers():
    return {
        "Authorization": f"Bearer {API_TOKEN}",
        "Content-Type": "application/json",
    }


def get_known_posts(account_name):
    response = requests.get(
        f"{LARAVEL_API_URL.rstrip('/')}/known-posts",
        params={"account": account_name},
        headers=api_headers(),
        timeout=API_TIMEOUT_SECONDS,
    )
    response.raise_for_status()
    return set(response.json()["shortcodes"])


def mark_posts_seen(account_name, posts):
    if not posts:
        return

    for index in range(0, len(posts), 500):
        response = requests.post(
            f"{LARAVEL_API_URL.rstrip('/')}/seen-posts",
            json={"account": account_name, "posts": posts[index:index + 500]},
            headers=api_headers(),
            timeout=API_TIMEOUT_SECONDS,
        )
        response.raise_for_status()


def get_post_urls(page, account_name):
    links = page.locator('a[href*="/p/"], a[href*="/reel/"]').evaluate_all(
        "(links) => links.map((link) => link.href)"
    )
    post_urls = []
    for link in links:
        parsed = urlsplit(link)
        segments = parsed.path.strip("/").split("/")
        if (
            parsed.hostname not in {"www.instagram.com", "instagram.com"}
            or len(segments) < 3
            or segments[0].casefold() != account_name.casefold()
            or segments[1] not in {"p", "reel", "tv"}
        ):
            continue

        post_urls.append(f"https://www.instagram.com/{segments[0]}/{segments[1]}/{segments[2]}/")

    return list(dict.fromkeys(post_urls))


def get_post_details(page):
    if "/accounts/login" in page.url or "/challenge/" in page.url:
        raise RuntimeError("Instagram mengalihkan postingan ke halaman login atau challenge.")

    page_html = page.content()
    timestamp_match = re.search(r'"taken_at"\s*:\s*(\d+)', page_html)
    if not timestamp_match:
        raise RuntimeError(f"Tanggal postingan tidak ditemukan: {page.url}")

    posted_at = datetime.fromtimestamp(int(timestamp_match.group(1)), tz=timezone.utc)
    for selector in ('meta[property="og:description"]', 'meta[name="description"]'):
        description = page.locator(selector).get_attribute("content")
        if description:
            return posted_at, description.strip()

    articles = page.locator("article").all_text_contents()
    caption = " ".join(text.strip() for text in articles if text.strip())
    return posted_at, caption


def send_match(account_name, post_url, caption, posted_at):
    payload = {
        "title": f"Event Tenant Call - @{account_name}",
        "description": caption,
        "location_name": "Jakarta / Depok (Perlu Validasi)",
        "latitude": -6.2,
        "longitude": 106.816666,
        "price": parse_price(caption),
        "instagram_post_url": post_url,
        "registration_link": f"https://instagram.com/{account_name}",
        "account_name": account_name,
        "posted_at": posted_at.isoformat(),
        "deadline_date": parse_deadline(caption),
    }
    headers = {
        "Authorization": f"Bearer {API_TOKEN}",
        "Content-Type": "application/json",
    }

    response = requests.post(
        LARAVEL_API_URL,
        json=payload,
        headers=headers,
        timeout=API_TIMEOUT_SECONDS,
    )
    if not response.ok:
        raise requests.HTTPError(
            f"Laravel API membalas {response.status_code}: {response.text}",
            response=response,
        )
    print(f"  [->] Laravel API: {response.status_code} - {response.text}")


def process_account(profile_page, post_page, account_name, cutoff, known_posts=None):
    profile_url = f"https://www.instagram.com/{account_name}/"
    print(f"\n[+] Checking Account: @{account_name}")
    known_posts = known_posts or set()

    profile_page.goto(profile_url, wait_until="domcontentloaded", timeout=15000)
    profile_page.wait_for_timeout(2000)

    if "/accounts/login" in profile_page.url or "/challenge/" in profile_page.url:
        raise RuntimeError("Instagram mengalihkan scraper ke halaman login atau challenge.")

    seen_urls = set()
    newly_seen_posts = []
    stalled_scrolls = 0
    saw_recent_post = False
    found_delta_boundary = False

    while stalled_scrolls < MAX_STALLED_SCROLLS:
        post_urls = get_post_urls(profile_page, account_name)
        new_urls = [url for url in post_urls if url not in seen_urls]
        seen_urls.update(new_urls)

        for post_url in new_urls:
            shortcode = post_shortcode(post_url)
            if shortcode in known_posts:
                if saw_recent_post:
                    print(f"  [✓] Delta selesai: postingan lama @{account_name} ditemukan.")
                    found_delta_boundary = True
                    break

                print(f"  [-] Postingan tersimpan/pinned dilewati: {post_url}")
                continue

            post_page.goto(post_url, wait_until="domcontentloaded", timeout=8000)
            posted_at, caption = get_post_details(post_page)
            newly_seen_posts.append({
                "shortcode": shortcode,
                "posted_at": posted_at.isoformat(),
            })

            if posted_at < cutoff:
                if saw_recent_post:
                    print(
                        f"  [✓] Timeline @{account_name} selesai: "
                        f"{posted_at.date()} lebih lama dari {cutoff.date()}."
                    )
                    mark_posts_seen(account_name, newly_seen_posts)
                    return

                print(f"  [-] Postingan lama/pinned dilewati: {post_url} ({posted_at.date()})")
                continue

            saw_recent_post = True

            if contains_keywords(caption):
                print(f"  [🔥] MATCH FOUND! @{account_name}: {post_url}")
                send_match(account_name, post_url, caption, posted_at)
            else:
                print(f"  [-] Postingan tidak cocok: {post_url} ({posted_at.date()})")

        if found_delta_boundary:
            break

        if not post_urls and not seen_urls:
            raise NoPublicPostsError(
                "Instagram tidak menampilkan link postingan publik untuk akun ini."
            )

        profile_page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
        profile_page.wait_for_timeout(PROFILE_SCROLL_PAUSE_MS)

        loaded_urls = get_post_urls(profile_page, account_name)
        if len(loaded_urls) <= len(seen_urls):
            stalled_scrolls += 1
        else:
            stalled_scrolls = 0

    mark_posts_seen(account_name, newly_seen_posts)

    if found_delta_boundary:
        return

    print(
        f"  [!] Instagram tidak memuat postingan tambahan @{account_name} setelah "
        f"{len(seen_urls)} postingan; semua postingan yang tersedia sudah diperiksa, "
        f"tetapi batas tanggal {cutoff.date()} belum terlihat."
    )


def main():
    if not API_TOKEN:
        raise RuntimeError("SCRAPER_API_TOKEN belum diatur di scraper/.env.")

    storage_state = Path(__file__).with_name("instagram-state.json")
    cutoff = subtract_months(datetime.now(timezone.utc), TIMELINE_MONTHS)
    print(f"[+] Batas tanggal scraping: {cutoff.date()} (3 bulan terakhir).")

    failures = []
    inaccessible_accounts = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            context_options = {
                "user_agent": (
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) "
                    "Chrome/120.0.0.0 Safari/537.36"
                )
            }
            if storage_state.is_file():
                context_options["storage_state"] = str(storage_state)
                print("[+] Menggunakan sesi Instagram tersimpan.")
            else:
                print("[+] Mencoba scraping tanpa login (akun publik saja).")

            context = browser.new_context(locale="en-US", **context_options)
            context.route(
                "**/*",
                lambda route: route.abort()
                if route.request.resource_type in {"image", "media", "font", "stylesheet"}
                else route.continue_(),
            )
            profile_page = context.new_page()
            post_page = context.new_page()

            for account in TARGET_ACCOUNTS:
                try:
                    known_posts = get_known_posts(account)
                    process_account(profile_page, post_page, account, cutoff, known_posts)
                except NoPublicPostsError as error:
                    print(f"  [!] @{account}: {error}")
                    inaccessible_accounts.append(account)
                except Exception as error:
                    print(f"  [x] Error pada @{account}: {error}")
                    failures.append(account)
                time.sleep(3)
        finally:
            browser.close()

    if failures:
        failed_accounts = ", ".join(f"@{account}" for account in failures)
        raise RuntimeError(f"Scraper gagal memproses akun: {failed_accounts}")

    if len(inaccessible_accounts) == len(TARGET_ACCOUNTS):
        accounts = ", ".join(f"@{account}" for account in inaccessible_accounts)
        raise RuntimeError(
            "Scraping anonim tidak menemukan postingan yang bisa dibaca pada akun: "
            f"{accounts}. Instagram tidak membuka daftar postingan publik untuk scraper ini."
        )


if __name__ == "__main__":
    main()
