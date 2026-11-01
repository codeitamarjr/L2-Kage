import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { useHashScroll, useParallax, useReveal, useScrolled } from '@/hooks/use-page-effects';
import { pick, useLocale, useT, type Localized } from '@/i18n';
import type { SharedProps } from '@/types';

type NavItem = { label: Localized; href: string; page?: string };

// Section links on the home page are plain anchors; the other pages are Inertia visits.
const NAV: NavItem[] = [
    { label: { en: 'World', pt: 'Mundo' }, href: '/#world' },
    { label: { en: 'Races', pt: 'Raças' }, href: '/#races' },
    { label: { en: 'News', pt: 'Notícias' }, href: '/news', page: 'news' },
    { label: { en: 'Map', pt: 'Mapa' }, href: '/map', page: 'map' },
    { label: { en: 'Items', pt: 'Itens' }, href: '/items', page: 'items' },
    { label: { en: 'Lore', pt: 'História' }, href: '/lore', page: 'lore' },
    { label: { en: 'Store', pt: 'Loja' }, href: '/store', page: 'store' },
    { label: { en: 'Features', pt: 'Recursos' }, href: '/#features' },
    { label: { en: 'About', pt: 'Sobre' }, href: '/about', page: 'about' },
];

/**
 * The dark | light switch. Dark is the default; the choice is saved in this browser and applied before the page paints
 * (resources/views/app.blade.php).
 */
function ThemeToggle() {
    const t = useT();
    const [light, setLight] = useState(false);

    useEffect(() => setLight(document.documentElement.dataset.theme === 'light'), []);

    const toggle = () => {
        const next = !light;
        setLight(next);

        if (next) {
            document.documentElement.dataset.theme = 'light';
        } else {
            delete document.documentElement.dataset.theme;
        }

        try {
            localStorage.setItem('theme', next ? 'light' : 'dark');
        } catch {
            // Private mode or blocked storage: the theme still applies until the next page load.
        }
    };

    return (
        <button
            type="button"
            className="theme-toggle"
            onClick={toggle}
            aria-pressed={light}
            aria-label={light ? t('Switch to the dark theme', 'Mudar para o tema escuro') : t('Switch to the light theme', 'Mudar para o tema claro')}
            title={light ? t('Dark theme', 'Tema escuro') : t('Light theme', 'Tema claro')}
        >
            {light ? (
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
                </svg>
            ) : (
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                </svg>
            )}
        </button>
    );
}

/** The EN | PT switch: a full page load, so the server renders the page (and its title) in the new language. */
function LanguageSwitch() {
    const locale = useLocale();
    const t = useT();

    return (
        <div className="lang-switch" role="group" aria-label={t('Language', 'Idioma')}>
            {(['en', 'pt_BR'] as const).map((code) => (
                <a key={code} href={`/locale/${code}`} lang={code === 'pt_BR' ? 'pt-BR' : 'en'} aria-current={locale === code ? 'true' : undefined}>
                    {code === 'pt_BR' ? 'PT' : 'EN'}
                </a>
            ))}
        </div>
    );
}

export default function SiteLayout({ children }: { children: ReactNode }) {
    const { component, url, props: { serverName, seo, player } } = usePage<SharedProps & { seo?: { title: string } }>();
    const scrolled = useScrolled();
    const [menuOpen, setMenuOpen] = useState(false);
    const locale = useLocale();
    const t = useT();

    useParallax(url);
    useReveal(url);
    useHashScroll(url);

    useEffect(() => setMenuOpen(false), [url]);
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setMenuOpen(false);
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, []);

    return (
        <main>
            {/* The same title the server put in the page (App\Services\Seo), kept while browsing. */}
            <Head title={seo?.title} />
            <header className={`site-header${scrolled ? ' is-scrolled' : ''}`}>
                <div className="header-inner">
                    <Link href="/" className="brand text-gold">
                        L2 {serverName.toUpperCase()}
                    </Link>
                    <nav className={`nav${menuOpen ? ' is-open' : ''}`} id="site-nav">
                        {NAV.map((item) =>
                            item.page ? (
                                <Link key={item.href} href={item.href} aria-current={component === item.page || component.startsWith(`${item.page}/`) ? 'page' : undefined}>
                                    {pick(locale, item.label)}
                                </Link>
                            ) : (
                                <a key={item.href} href={component === 'home' ? item.href.slice(1) : item.href} onClick={() => setMenuOpen(false)}>
                                    {pick(locale, item.label)}
                                </a>
                            ),
                        )}
                    </nav>
                    <button
                        className="menu-toggle"
                        type="button"
                        aria-expanded={menuOpen}
                        aria-controls="site-nav"
                        aria-label={menuOpen ? t('Close menu', 'Fechar menu') : t('Open menu', 'Abrir menu')}
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <LanguageSwitch />
                    <ThemeToggle />
                    {player ? (
                        // Signed in: the account's initial, so players see at a glance they're logged in.
                        <Link
                            href="/account"
                            className={player.premium ? 'header-avatar is-premium' : 'header-avatar'}
                            aria-current={component === 'account' ? 'page' : undefined}
                            aria-label={
                                player.premium
                                    ? t(`Your account (${player.login}, premium)`, `Sua conta (${player.login}, premium)`)
                                    : t(`Your account (${player.login})`, `Sua conta (${player.login})`)
                            }
                            title={player.premium ? `${player.login} · Premium` : player.login}
                        >
                            {player.login.charAt(0).toUpperCase()}
                            {player.gifts > 0 ? (
                                <span className="header-gifts" aria-label={t(`${player.gifts} waiting in your orders`, `${player.gifts} aguardando nos seus pedidos`)}>
                                    {player.gifts}
                                </span>
                            ) : null}
                            {player.premium ? (
                                // Premium: a small star on the ring.
                                <span className="header-avatar-star" aria-hidden="true">
                                    ★
                                </span>
                            ) : null}
                        </Link>
                    ) : (
                        <Link href="/account" className="header-cta" aria-current={component === 'account' ? 'page' : undefined}>
                            {t('Account', 'Conta')}
                        </Link>
                    )}
                </div>
            </header>

            {children}

            <footer className="site-footer">
                <p className="footer-brand">LINEAGE II · ESSENCE · SAMURAI CROW</p>
                <p>
                    {t(
                        <>L2 {serverName} is a free, non-commercial fan server, not affiliated with or endorsed by NCSOFT. Lineage II is a trademark of NCSOFT Corporation.</>,
                        <>L2 {serverName} é um servidor de fãs gratuito e sem fins lucrativos, sem vínculo nem endosso da NCSOFT. Lineage II é uma marca registrada da NCSOFT Corporation.</>,
                    )}
                </p>
                <p>
                    {t('Powered by', 'Feito com')}{' '}
                    <a className="text-link" href="https://gitlab.com/MobiusDevelopment/L2J_Mobius" target="_blank" rel="noopener noreferrer">
                        L2J Mobius
                    </a>{' '}
                    (GNU GPL v3).
                </p>
                {/* l2list.com only lists a server for free with its button on the home page. */}
                <p>
                    <a href="https://l2list.com" className="toplist-button" title="New private servers Lineage 2" target="_blank" rel="noopener">
                        <img src="/assets/l2list-button.png" width={150} height={38} alt="New private servers Lineage 2" />
                    </a>
                </p>
            </footer>
        </main>
    );
}
