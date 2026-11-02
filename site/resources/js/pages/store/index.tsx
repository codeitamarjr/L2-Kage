import { Link, useRemember } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PageHero from '@/components/page-hero';
import { ProductCard, ShopBar } from '@/components/store';
import { languageTag, pick, useLocale, useT, type Localized } from '@/i18n';
import type { StoreCard, StoreCategory } from '@/types';

type Props = {
    products: StoreCard[];
    /** The categories with something to show, after the banner's own Main and L-Coin. */
    categories: StoreCategory[];
    filter: { category: string; group: string | null; q: string };
    /** Signed in: the products the account already holds. */
    owned: string[];
    /** Signed in: the class of the highest level character, whose packages come right after what's listed first. */
    suggested: string | null;
    lcoin: { welcome: number; giftBox: number };
};

/** The class packages listed first on the main page until a signed-in character says otherwise: this update's own class. */
const DEFAULT_ARCHETYPE = 'samurai';

/** How many more cards appear each time the player nears the end of the grid. */
const PAGE_SIZE = 12;

/** The fixed site header, which the products stop under when scrolled to. */
const HEADER_HEIGHT = 69;

/** Where L-Coin comes from and what it buys. It isn't sold here, so this takes the grid's place. */
function LCoinInfo({ products, lcoin }: Pick<Props, 'products' | 'lcoin'>) {
    const locale = useLocale();
    const t = useT();
    const fmt = (n: number) => n.toLocaleString(languageTag(locale));
    const cost = (slug: string) => {
        const price = products.find((p) => p.slug === slug)?.price;

        return price ? `${fmt(price.count)} ${price.name}` : '';
    };
    const sources = [
        {
            title: t('Welcome gift', 'Presente de boas-vindas'),
            text: t(`Every new character starts with ${fmt(lcoin.welcome)} L-Coin in the bag.`, `Todo personagem novo começa com ${fmt(lcoin.welcome)} L-Coin na bolsa.`),
        },
        ...(lcoin.giftBox
            ? [
                  {
                      title: t('Weekly gift box', 'Caixa de presente semanal'),
                      text: t(`Every box brings ${fmt(lcoin.giftBox)} L-Coin more.`, `Toda caixa traz mais ${fmt(lcoin.giftBox)} L-Coin.`),
                  },
              ]
            : []),
        {
            title: t('What it buys', 'O que ela compra'),
            text: t(
                `The Kage Pass (${cost('kage-pass')}), the class packages, Hunt Pass Premium (${cost('hunt-pass')}) and the L-Coin shop in game.`,
                `O Kage Pass (${cost('kage-pass')}), os pacotes de classe, o Hunt Pass Premium (${cost('hunt-pass')}) e a loja de L-Coin no jogo.`,
            ),
        },
    ];

    return (
        <div className="shop-info">
            <h2 className="shop-heading text-gold">L-Coin</h2>
            <p>
                {t(
                    'Prices are paid from a character’s bag (not the warehouse), and the site shows the last save: every 5 minutes while you play, and when you log out.',
                    'Os preços são pagos da bolsa de um personagem (não do armazém), e o site mostra o último salvamento: a cada 5 minutos enquanto você joga, e ao sair.',
                )}
            </p>
            <div className="shop-info-grid">
                {sources.map((source) => (
                    <section key={source.title}>
                        <h2>{source.title}</h2>
                        <p>{source.text}</p>
                    </section>
                ))}
            </div>
        </div>
    );
}

/**
 * The Store (/store), under the site's banner and laid out like an official game store: category buttons in the banner above a grid of products, each
 * opening its own page with the purchase panel (store/show). Filters live in the address so they can be shared and
 * survive the back button; the quick search filters what is already on the page. Every product is already on the page
 * too, so the grid only adds its cards as the player scrolls, which keeps the images from loading all at once.
 */
export default function StoreIndex({ products, categories, filter, owned, suggested, lcoin }: Props) {
    const locale = useLocale();
    const t = useT();
    const [query, setQuery] = useState(filter.q);
    // Remembered with how far the grid was scrolled, so Back from a product returns to the same place.
    const [price, setPrice] = useRemember('all', 'store-price');
    const [sort, setSort] = useRemember<'recommended' | 'low' | 'high' | 'name'>('recommended', 'store-sort');
    const [loaded, setLoaded] = useRemember({ view: '', count: PAGE_SIZE }, 'store-loaded');
    const endOfGrid = useRef<HTMLDivElement>(null);
    // Choosing a category, or arriving from elsewhere (Back from a product, the home page), goes straight to the products, past the banner.
    useEffect(() => {
        if (window.location.hash === '#store') {
            const top = document.getElementById('store')?.getBoundingClientRect().top ?? 0;

            window.scrollTo({ top: window.scrollY + top - HEADER_HEIGHT, behavior: 'instant' });
        }
    }, [filter.category, filter.group]);

    const term = query.trim().toLowerCase();
    const featured = suggested ?? DEFAULT_ARCHETYPE;

    const buttons: { key: string; label: string; href: string }[] = [
        { key: 'main', label: t('Main', 'Principal'), href: '/store#store' },
        { key: 'lcoin', label: 'L-Coin', href: '/store?category=lcoin#store' },
        ...categories.map((category) => ({ key: category.key, label: pick(locale, category.name), href: `/store?category=${category.key}#store` })),
    ];

    // The current category's class groups, in the catalogue's order.
    const groups = useMemo(() => {
        const seen = new Map<string, Localized>();
        products.filter((p) => p.category === filter.category).forEach((p) => p.group && p.groupName && seen.set(p.group, p.groupName));

        return [...seen];
    }, [products, filter.category]);

    const visible = products.filter((p) => {
        if (term) {
            return p.name.en.toLowerCase().includes(term) || p.name.pt.toLowerCase().includes(term);
        }

        if (filter.category === 'main') {
            return true;
        }

        return p.category === filter.category && (!filter.group || p.group === filter.group);
    });

    // Which prices this view has, and the visible products narrowed and ordered by the toolbar.
    const currencies = [...new Set(visible.flatMap((p) => (p.price ? [p.price.name] : [])))];
    const hasFree = visible.some((p) => !p.price);
    const priceFilters = [...(hasFree ? [{ key: 'free', label: t('Free', 'Grátis') }] : []), ...currencies.map((name) => ({ key: name, label: name }))];
    // On the main page: what's listed first, then the packages for the player's class, then everything else.
    const rank = (p: StoreCard) => (filter.category !== 'main' || term ? 0 : p.featured ? 0 : p.group === featured ? 1 : 2);
    const matching = visible
        .filter((p) => price === 'all' || (price === 'free' ? !p.price : p.price?.name === price))
        .map((p, i) => ({ p, i }))
        .sort((a, b) => {
            if (sort === 'name') {
                return pick(locale, a.p.name).localeCompare(pick(locale, b.p.name), languageTag(locale));
            }

            if (sort === 'recommended') {
                return rank(a.p) - rank(b.p) || a.i - b.i;
            }

            // Different currencies can't be compared: free first, then each currency by its count.
            const currency = (a.p.price?.name ?? '').localeCompare(b.p.price?.name ?? '');

            return currency || ((a.p.price?.count ?? 0) - (b.p.price?.count ?? 0)) * (sort === 'low' ? 1 : -1);
        })
        .map(({ p }) => p);
    const count = matching.length;

    // Any change to what's listed starts again from the first cards.
    const view = [filter.category, filter.group, term, price, sort].join('|');
    const limit = loaded.view === view ? loaded.count : PAGE_SIZE;
    const shown = matching.slice(0, limit);

    // Watches again after each load, so a screen tall enough to still show the end of the grid keeps loading.
    useEffect(() => {
        const end = endOfGrid.current;

        if (!end || limit >= count) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    setLoaded({ view, count: limit + PAGE_SIZE });
                }
            },
            { rootMargin: '400px' },
        );

        observer.observe(end);

        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [view, limit, count]);

    return (
        <div className="shop">
            <PageHero
                video={{ src: '/assets/video/treasury.mp4', poster: '/assets/packages-chest.webp' }}
                kicker={t('Gifts, passes & packages', 'Presentes, passes e pacotes')}
                title={t('The Store', 'A Loja')}
                tagline={t(
                    'A gift from Einhasad every week, the pacts of the season and arms forged for every calling of Aden.',
                    'Um presente de Einhasad toda semana, os pactos da temporada e armas forjadas para cada vocação de Aden.',
                )}
            >
                <nav className="store-nav" aria-label={t('Categories', 'Categorias')}>
                    {buttons.map((button) => (
                        <Link key={button.key} href={button.href} preserveScroll aria-current={!term && filter.category === button.key ? 'page' : undefined} onClick={() => { setQuery(''); setPrice('all'); }}>
                            {button.label}
                        </Link>
                    ))}
                </nav>
            </PageHero>
            <ShopBar query={query} onQuery={setQuery} />
            <div className="shop-layout">

                <div className="shop-main">
                    {groups.length > 1 && !term ? (
                        <nav className="shop-sub" aria-label={t('Class', 'Classe')}>
                            <Link href={`/store?category=${filter.category}#store`} preserveScroll aria-current={!filter.group ? 'page' : undefined}>
                                {t('All', 'Todos')}
                            </Link>
                            {groups.map(([key, name]) => (
                                <Link key={key} href={`/store?category=${filter.category}&group=${key}#store`} preserveScroll aria-current={filter.group === key ? 'page' : undefined}>
                                    {pick(locale, name)}
                                </Link>
                            ))}
                        </nav>
                    ) : null}
                    {filter.category === 'lcoin' && !term ? (
                        <LCoinInfo products={products} lcoin={lcoin} />
                    ) : visible.length ? (
                        <>
                            <div className="shop-toolbar">
                                {priceFilters.length > 1 ? (
                                    <div className="shop-chips" role="group" aria-label={t('Price', 'Preço')}>
                                        <button type="button" aria-pressed={price === 'all'} onClick={() => setPrice('all')}>
                                            {t('All', 'Todos')}
                                        </button>
                                        {priceFilters.map((filterOption) => (
                                            <button key={filterOption.key} type="button" aria-pressed={price === filterOption.key} onClick={() => setPrice(filterOption.key)}>
                                                {filterOption.label}
                                            </button>
                                        ))}
                                    </div>
                                ) : null}
                                <label className="shop-sort">
                                    <span>{t('Sort by', 'Ordenar por')}</span>
                                    <select value={sort} onChange={(e) => setSort(e.target.value as typeof sort)}>
                                        <option value="recommended">{t('Recommended', 'Recomendados')}</option>
                                        <option value="low">{t('Price: low to high', 'Preço: menor para maior')}</option>
                                        <option value="high">{t('Price: high to low', 'Preço: maior para menor')}</option>
                                        <option value="name">{t('Name', 'Nome')}</option>
                                    </select>
                                </label>
                            </div>
                            {count ? (
                                <>
                                    <div className="shop-grid">
                                        {shown.map((product) => (
                                            <ProductCard key={product.slug} product={product} owned={owned.includes(product.slug)} />
                                        ))}
                                    </div>
                                    <div ref={endOfGrid} className="shop-count" aria-live="polite">
                                        {shown.length < count
                                            ? t(`Showing ${shown.length} of ${count} products`, `Mostrando ${shown.length} de ${count} produtos`)
                                            : t(`${count} ${count === 1 ? 'product' : 'products'}`, `${count} ${count === 1 ? 'produto' : 'produtos'}`)}
                                    </div>
                                </>
                            ) : (
                                <p className="shop-empty">{t('Nothing here at this price.', 'Nada aqui neste preço.')}</p>
                            )}
                        </>
                    ) : (
                        <p className="shop-empty">{t('Nothing in the Store matches your search.', 'Nada na Loja corresponde à sua busca.')}</p>
                    )}
                </div>
            </div>
        </div>
    );
}
