from pathlib import Path

from playwright.sync_api import sync_playwright


def main():
    storage_state = Path(__file__).with_name("instagram-state.json")

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=False)
        context = browser.new_context()
        page = context.new_page()
        page.goto("https://www.instagram.com/accounts/login/", wait_until="domcontentloaded")

        input("Login Instagram di browser yang terbuka, lalu tekan Enter di terminal ini...")
        if "/accounts/login" in page.url or "/challenge/" in page.url:
            browser.close()
            raise RuntimeError(
                "Login belum selesai atau Instagram meminta verifikasi; sesi tidak disimpan."
            )

        context.storage_state(path=str(storage_state))
        browser.close()

    print(f"Sesi Instagram disimpan secara lokal di: {storage_state}")
    print("Jangan bagikan atau commit file sesi ini; file berisi cookie autentikasi.")


if __name__ == "__main__":
    main()
