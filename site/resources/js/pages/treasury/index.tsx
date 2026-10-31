import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
            <h1 className="shop-heading">L-Coin</h1>
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
 * The Treasury (/treasury), laid out like an official game store: a category tree beside a grid of products, each
 * opening its own page with the purchase panel (treasury/show). Filters live in the address so they can be shared and
 * survive the back button; the quick search filters what is already on the page.
 */
export default function TreasuryIndex({ products, filter, owned, suggested, lcoin }: Props) {
    const locale = useLocale();
    const t = useT();
    const [query, setQuery] = useState(filter.q);
    const term = query.trim().toLowerCase();
    const featured = suggested ?? DEFAULT_ARCHETYPE;

    const categories: { key: Props['filter']['category']; label: string; href: string }[] = [
        { key: 'main', label: t('Main', 'Principal'), href: '/treasury' },
        { key: 'lcoin', label: 'L-Coin', href: '/treasury?category=lcoin' },
        { key: 'gifts', label: t('Gifts', 'Presentes'), href: '/treasury?category=gifts' },
        { key: 'passes', label: t('Passes', 'Passes'), href: '/treasury?category=passes' },
        { key: 'packages', label: t('Class Packages', 'Pacotes de Classe'), href: '/treasury?category=packages' },
        { key: 'supplies', label: t('Supplies', 'Suprimentos'), href: '/treasury?category=supplies' },
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
            <ShopBar query={query} onQuery={setQuery} />
            <div className="shop-layout">
                <nav className="shop-side" aria-label={t('Categories', 'Categorias')}>
                    <ul>
                        {categories.map((category) => (
                            <li key={category.key}>
                                <Link
                                    href={category.href}
                                    preserveScroll
                                    className="shop-side-link"
                                    aria-current={!term && filter.category === category.key && !filter.group ? 'page' : undefined}
                                    onClick={() => setQuery('')}
                                >
                                    {category.label}
                                </Link>
                                {category.key === 'packages' && filter.category === 'packages' && !term ? (
                                    <ul className="shop-side-sub">
                                        {groups.map(([key, name]) => (
                                            <li key={key}>
                                                <Link href={`/treasury?category=packages&group=${key}`} preserveScroll className="shop-side-link" aria-current={filter.group === key ? 'page' : undefined}>
                                                    {pick(locale, name)}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="shop-main">
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
