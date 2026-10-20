import React, { useState } from 'react';

interface EventTenant {
    id: number;
    tenant_type: string;
    price: number | null;
    requirements: string | null;
    registration_link: string | null;
    instagram_post_url: string | null;
    deadline_date: string | null;
}

interface EventItem {
    id: number;
    title: string;
    category: string;
    location_name: string;
    start_date: string;
    end_date: string;
    source_account: string | null;
    priority_score: number;
    is_priority: boolean;
    tenants: EventTenant[];
}

type EventFilter = 'all' | 'priority' | 'urgent';

interface Props {
    events: EventItem[];
}

export default function EventRadar({ events }: Props) {
    const [filter, setFilter] = useState<EventFilter>('all');

    const isUrgent = (event: EventItem) => {
        const deadline = event.tenants[0]?.deadline_date;
        if (!deadline) return false;
        const deadlineTime = new Date(deadline).getTime();
        const now = Date.now();
        return deadlineTime >= now && deadlineTime < now + 3 * 24 * 60 * 60 * 1000;
    };

    const visibleEvents = [...events]
        .filter((event) => {
            if (filter === 'priority') return event.is_priority;
            if (filter === 'urgent') return isUrgent(event);
            return true;
        })
        .sort((a, b) => Number(isUrgent(b)) - Number(isUrgent(a)) || b.priority_score - a.priority_score);

    return (
        <div style={{ padding: '32px', backgroundColor: '#0f172a', minHeight: '100vh', color: '#f8fafc', fontFamily: 'sans-serif' }}>
            <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
                <header style={{ marginBottom: '32px' }}>
                    <h1 style={{ fontSize: '28px', fontWeight: 'bold', color: '#38bdf8' }}>
                        📡 Event & Tenant Radar
                    </h1>
                    <p style={{ color: '#94a3b8', fontSize: '14px', marginTop: '4px' }}>
                        Daftar peluang bazaar & event kuliner hasil pemeriksaan Instagram otomatis pukul 08.17 dan 20.17 WIB.
                    </p>
                </header>

                <nav aria-label="Filter event" style={{ display: 'flex', gap: '8px', marginBottom: '20px' }}>
                    {([
                        ['all', 'Semua Event'],
                        ['priority', '🔥 Event Besar/Priority'],
                        ['urgent', '⏳ Segera Tutup'],
                    ] as const).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={filter === value}
                            onClick={() => setFilter(value)}
                            style={{
                                padding: '8px 12px',
                                borderRadius: '6px',
                                border: '1px solid #334155',
                                backgroundColor: filter === value ? '#0369a1' : '#1e293b',
                                color: '#f8fafc',
                                cursor: 'pointer',
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </nav>

                {visibleEvents.length === 0 ? (
                    <div style={{ padding: '40px', textAlign: 'center', backgroundColor: '#1e293b', borderRadius: '12px' }}>
                        <p style={{ color: '#94a3b8' }}>
                            {events.length === 0 ? 'Belum ada data event. Pemeriksaan otomatis berikutnya pukul 03.00 atau 18.00 WIB.' : 'Tidak ada event pada filter ini.'}
                        </p>
                    </div>
                ) : (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
                        {visibleEvents.map((item) => {
                            const tenantInfo = item.tenants[0]; // Ambil detail tenant pertama

                            return (
                                <div 
                                    key={item.id} 
                                    style={{ 
                                        backgroundColor: '#1e293b', 
                                        borderRadius: '12px', 
                                        padding: '20px', 
                                        border: '1px solid #334155',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        justifyContent: 'space-between'
                                    }}
                                >
                                    <div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                            <span style={{ 
                                                backgroundColor: '#0284c7', 
                                                color: '#fff', 
                                                padding: '2px 8px', 
                                                borderRadius: '4px', 
                                                fontSize: '11px', 
                                                fontWeight: 'bold',
                                                textTransform: 'uppercase'
                                            }}>
                                                {item.category}
                                            </span>
                                            <span style={{ fontSize: '12px', color: '#64748b' }}>
                                                📍 {item.location_name}
                                            </span>
                                        </div>

                                        <div style={{ display: 'flex', gap: '6px', marginBottom: '10px' }}>
                                            {item.is_priority && (
                                                <span style={{ background: 'linear-gradient(90deg, #dc2626, #f97316)', color: '#fff', padding: '3px 8px', borderRadius: '999px', fontSize: '11px', fontWeight: 700 }}>
                                                    🔥 PRIORITAS · {item.priority_score}
                                                </span>
                                            )}
                                            {item.source_account && ['pestapora', 'synchronizefest', 'jktgo'].includes(item.source_account) && (
                                                <span style={{ backgroundColor: '#7c2d12', color: '#fed7aa', padding: '3px 8px', borderRadius: '999px', fontSize: '11px', fontWeight: 700 }}>
                                                    TIER 1 · @{item.source_account}
                                                </span>
                                            )}
                                            {isUrgent(item) && (
                                                <span style={{ backgroundColor: '#9f1239', color: '#ffe4e6', padding: '3px 8px', borderRadius: '999px', fontSize: '11px', fontWeight: 700 }}>
                                                    ⏳ URGENT · SEGERA DRAFT
                                                </span>
                                            )}
                                        </div>

                                        <h2 style={{ fontSize: '18px', fontWeight: '600', color: '#f1f5f9', marginBottom: '8px' }}>
                                            {item.title}
                                        </h2>

                                        <p style={{ 
                                            fontSize: '13px', 
                                            color: '#cbd5e1', 
                                            lineHeight: '1.5', 
                                            display: '-webkit-box', 
                                            WebkitLineClamp: 3, 
                                            WebkitBoxOrient: 'vertical', 
                                            overflow: 'hidden',
                                            marginBottom: '16px'
                                        }}>
                                            {tenantInfo?.requirements || 'Tidak ada deskripsi caption.'}
                                        </p>
                                    </div>

                                    <div style={{ borderTop: '1px solid #334155', paddingTop: '12px', marginTop: '12px' }}>
                                        <div style={{ fontSize: '14px', fontWeight: 'bold', color: '#4ade80', marginBottom: '12px' }}>
                                            💰 {tenantInfo?.price ? `Rp ${Number(tenantInfo.price).toLocaleString('id-ID')}` : 'Hubungi Organizer'}
                                        </div>

                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            {tenantInfo?.instagram_post_url && (
                                                <a 
                                                    href={tenantInfo.instagram_post_url} 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    style={{ 
                                                        flex: 1,
                                                        textAlign: 'center',
                                                        padding: '8px 12px', 
                                                        backgroundColor: '#e1306c', 
                                                        color: '#fff', 
                                                        borderRadius: '6px', 
                                                        textDecoration: 'none', 
                                                        fontSize: '12px', 
                                                        fontWeight: 'bold' 
                                                    }}
                                                >
                                                    Post IG ↗
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}