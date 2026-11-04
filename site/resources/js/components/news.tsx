import { Link } from '@inertiajs/react';
import { languageTag, useLocale, useT, type Locale, type Localized } from '@/i18n';
import type { NewsBanner, NewsCard as NewsCardData } from '@/types';

export const NEWS_CATEGORIES: Record<string, Localized> = {
    event: { en: 'Event', pt: 'Evento' },
    quest: { en: 'Quest', pt: 'Missão' },
    update: { en: 'Update', pt: 'Atualização' },
    announcement: { en: 'Announcement', pt: 'Anúncio' },
};

/** Dates are stored in UTC, and shown as such so a post's date doesn't change between visitors. */
export function newsDate(iso: string, locale: Locale, year = true): string {
    return new Date(iso).toLocaleDateString(languageTag(locale), { day: 'numeric', month: 'long', ...(year ? { year: 'numeric' } : {}), timeZone: 'UTC' });
}

export function NewsCard({ post, style }: { post: NewsCardData; style?: React.CSSProperties }) {
    const locale = useLocale();
    const category = NEWS_CATEGORIES[post.category];

    return (
        <Link href={`/news/${post.slug}`} className="reveal news-card" style={style}>
            {post.image ? (
                <span className="news-card-art">
                    <img src={post.image} alt="" loading="lazy" />
                </span>
            ) : null}
            <span className="news-card-meta">
                <span className="news-category">{category ? category[locale === 'pt_BR' ? 'pt' : 'en'] : post.category}</span>
                {post.publishedAt ? <time dateTime={post.publishedAt}>{newsDate(post.publishedAt, locale)}</time> : null}
            </span>
            <span className="news-card-title">{post.title}</span>
            <span className="news-card-summary">{post.summary}</span>
        </Link>
    );
}

export function Pumpkin({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 120 110" aria-hidden="true">
            <path d="M56 22c0-9 3-15 11-19l6 6c-6 3-8 7-8 13z" fill="#6f8f3a" />
            <ellipse cx="60" cy="65" rx="56" ry="42" fill="#c9570f" />
            <ellipse cx="60" cy="65" rx="38" ry="42" fill="#e06c14" />
            <ellipse cx="60" cy="65" rx="17" ry="42" fill="#f08a24" />
            <path d="M28 55l15-11 8 15zM92 55L77 44l-8 15zM60 61l-7 12h14zM24 78c10 4 16 2 20 5l5-5 6 6 5-6 5 6 6-6 5 5c4-3 10-1 20-5-5 15-20 22-36 22s-31-7-36-22z" fill="#ffe08a" />
        </svg>
    );
}

export function Snowflake({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 120 120" aria-hidden="true" fill="none" stroke="#dff3ff" strokeWidth="6" strokeLinecap="round">
            {[0, 60, 120].map((r) => (
                <g key={r} transform={`rotate(${r} 60 60)`}>
                    <path d="M60 8v104M60 26l-12-12M60 26l12-12M60 94l-12 12M60 94l12 12" />
                </g>
            ))}
            <circle cx="60" cy="60" r="9" fill="#9fd8ff" stroke="none" />
        </svg>
    );
}

/** The home page's banner: the featured post, until its end date. Written in the admin panel (News). */
export function FeaturedBanner({ banner }: { banner?: NewsBanner | null }) {
    const locale = useLocale();
    const t = useT();

    if (!banner) {
        return null;
    }

    const category = NEWS_CATEGORIES[banner.category];

    return (
        <section className={`featured-banner is-${banner.theme}`} aria-label={banner.title}>
            <div className="featured-inner">
                {banner.image ? <img className="featured-art featured-image" src={banner.image} alt="" /> : banner.theme === 'halloween' ? <Pumpkin className="featured-art" /> : banner.theme === 'winter' ? <Snowflake className="featured-art" /> : null}
                <div className="featured-copy">
                    <p className="featured-kicker">
                        {category ? category[locale === 'pt_BR' ? 'pt' : 'en'] : banner.category}
                        {banner.until ? ` · ${t('until', 'até')} ${newsDate(banner.until, locale, false)}` : ''}
                    </p>
                    <h2 className="featured-title">{banner.title}</h2>
                    <p className="featured-text">{banner.summary}</p>
                </div>
                <Link href={`/news/${banner.slug}`} className="featured-cta">
                    {t('Read more', 'Saiba mais')}
                </Link>
            </div>
        </section>
    );
}
