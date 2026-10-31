import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import PageHero from '@/components/page-hero';
import { ProductCard, ShopBar } from '@/components/store';
import { languageTag, pick, useLocale, useT, type Localized } from '@/i18n';
import type { StoreCard } from '@/types';

type Props = {
    products: StoreCard[];
    filter: { category: 'main' | 'lcoin' | 'gifts' | 'passes' | 'packages' | 'supplies'; group: string | null; q: string };
    /** Signed in: the products the account already holds. */
    owned: string[];
    /** Signed in: the class of the highest level character, whose packages the front page features. */
    suggested: string | null;
    lcoin: { welcome: number; giftBox: number };
};

/** The class packages on the front page until a signed-in character says otherwise: this update's own class. */
const DEFAULT_ARCHETYPE = 'samurai';

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
 * The Treasury (/treasury), under the site's banner and laid out like an official game store: category buttons in the banner above a grid of products, each
 * opening its own page with the purchase panel (treasury/show). Filters live in the address so they can be shared and
 * survive the back button; the quick search filters what is already on the page.
 */
export default function TreasuryIndex({ products, filter, owned, suggested, lcoin }: Props) {
    const locale = useLocale();
    const t = useT();
    const [query, setQuery] = useState(filter.q);
    // Choosing a category, or arriving from elsewhere (Back from a product, the home page), goes straight to the products, past the banner.
    useEffect(() => {
        if (window.location.hash === '#store') {
            requestAnimationFrame(() => {
                const top = document.getElementById('store')?.getBoundingClientRect().top ?? 0;

                window.scrollTo({ top: window.scrollY + top - HEADER_HEIGHT, behavior: 'instant' });
            });
        }
    }, [filter.category, filter.group]);

    const term = query.trim().toLowerCase();
    const featured = suggested ?? DEFAULT_ARCHETYPE;

    const categories: { key: Props['filter']['category']; label: string; href: string }[] = [
        { key: 'main', label: t('Main', 'Principal'), href: '/treasury#store' },
        { key: 'lcoin', label: 'L-Coin', href: '/treasury?category=lcoin#store' },
        { key: 'gifts', label: t('Gifts', 'Presentes'), href: '/treasury?category=gifts#store' },
        { key: 'passes', label: t('Passes', 'Passes'), href: '/treasury?category=passes#store' },
        { key: 'packages', label: t('Class Packages', 'Pacotes de Classe'), href: '/treasury?category=packages#store' },
        { key: 'supplies', label: t('Supplies', 'Suprimentos'), href: '/treasury?category=supplies#store' },
    ];

    // One entry per archetype, in the catalogue's order.
    const groups = useMemo(() => {
        const seen = new Map<string, Localized>();
        products.forEach((p) => p.group && p.groupName && seen.set(p.group, p.groupName));

        return [...seen];
    }, [products]);

    const visible = products.filter((p) => {
        if (term) {
            return p.name.en.toLowerCase().includes(term) || p.name.pt.toLowerCase().includes(term);
        }

        if (filter.category === 'main') {
            return p.kind !== 'package' || p.group === featured;
        }

        return p.category === filter.category && (!filter.group || p.group === filter.group);
    });

    return (
        <div className="shop">
            <PageHero
                video={{ src: '/assets/video/treasury.mp4', poster: '/assets/packages-chest.webp' }}
                kicker={t('Gifts, passes & packages', 'Presentes, passes e pacotes')}
                title={t('The Treasury', 'O Tesouro')}
                tagline={t(
                    'A gift from Einhasad every week, the pacts of the season and arms forged for every calling of Aden.',
                    'Um presente de Einhasad toda semana, os pactos da temporada e armas forjadas para cada vocação de Aden.',
                )}
            >
                <nav className="store-nav" aria-label={t('Categories', 'Categorias')}>
                    {categories.map((category) => (
                        <Link key={category.key} href={category.href} preserveScroll aria-current={!term && filter.category === category.key ? 'page' : undefined} onClick={() => setQuery('')}>
                            {category.label}
                        </Link>
                    ))}
                </nav>
            </PageHero>
            <ShopBar query={query} onQuery={setQuery} />
            <div className="shop-layout">

                <div className="shop-main">
                    {filter.category === 'packages' && !term ? (
                        <nav className="shop-sub" aria-label={t('Archetype', 'Arquétipo')}>
                            <Link href="/treasury?category=packages#store" preserveScroll aria-current={!filter.group ? 'page' : undefined}>
                                {t('All', 'Todos')}
                            </Link>
                            {groups.map(([key, name]) => (
                                <Link key={key} href={`/treasury?category=packages&group=${key}#store`} preserveScroll aria-current={filter.group === key ? 'page' : undefined}>
                                    {pick(locale, name)}
                                </Link>
                            ))}
                        </nav>
                    ) : null}
                    {filter.category === 'lcoin' && !term ? (
                        <LCoinInfo products={products} lcoin={lcoin} />
                    ) : visible.length ? (
                        <div className="shop-grid">
                            {visible.map((product) => (
                                <ProductCard key={product.slug} product={product} owned={owned.includes(product.slug)} />
                            ))}
                        </div>
                    ) : (
                        <p className="shop-empty">{t('Nothing in the Treasury matches your search.', 'Nada no Tesouro corresponde à sua busca.')}</p>
                    )}
                </div>
            </div>
        </div>
    );
}
