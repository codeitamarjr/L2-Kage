import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { GLYPHS } from '@/components/item-icon';
import { languageTag, pick, useLocale, useT } from '@/i18n';
import type { StoreCard } from '@/types';

/** Pistols are the one weapon the item icons don't draw. */
const PISTOL = <path d="M3 8h17v4h-7l-1 2h-2l-1 5H5l1-7H3z" />;

/** The drawing on a class package or supply kit, over its art. */
const EMBLEMS: Record<string, keyof typeof GLYPHS> = {
    knight: 'shield', samurai: 'sword', duelist: 'dual', titan: 'sword', lancer: 'pole', artisan: 'blunt', rogue: 'dagger',
    archer: 'bow', brawler: 'fist', kamael: 'sword', wizard: 'sigil', healer: 'potion', traveler: 'scroll', hunter: 'potion',
};

const ART: Record<StoreCard['art'], string> = {
    gift: '/assets/store/gift-chest.webp',
    pass: '/assets/store/pass-ticket.webp',
    hunt: '/assets/store/pass-ticket.webp',
    package: '/assets/store/package-chest.webp',
    supplies: '/assets/store/supply-case.webp',
};

/** A product's picture: its uploaded image, or else its drawn art faded into the card, tinted by tier, with an emblem. */
export function ProductArt({ product, image }: { product: StoreCard; image?: string }) {
    const locale = useLocale();
    const src = image ?? product.image;

    if (src) {
        return (
            <span className="shop-art has-image">
                <img src={src} alt={pick(locale, product.name)} width={520} height={520} loading="lazy" />
            </span>
        );
    }

    const { emblem } = product;

    return (
        <span className={`shop-art is-${product.art}${product.tier ? ` tier-${product.tier}` : ''}`}>
            <img src={ART[product.art]} alt="" width={520} height={520} loading="lazy" />
            {emblem ? (
                <span className="shop-art-emblem" aria-hidden="true">
                    <svg viewBox="0 0 24 24">{emblem === 'gunner' ? PISTOL : GLYPHS[EMBLEMS[emblem] ?? 'other']}</svg>
                </span>
            ) : null}
        </span>
    );
}

/** The number and its currency, or "Gift" when free. */
export function Price({ product, className }: { product: Pick<StoreCard, 'price'>; className?: string }) {
    const locale = useLocale();
    const t = useT();

    return (
        <span className={className}>
            {product.price ? (
                <>
                    {product.price.count.toLocaleString(languageTag(locale))} <small>{product.price.name}</small>
                </>
            ) : (
                t('Gift', 'Presente')
            )}
        </span>
    );
}

export function ProductCard({ product, owned }: { product: StoreCard; owned: boolean }) {
    const locale = useLocale();
    const t = useT();

    return (
        <Link href={`/store/${product.slug}`} className="shop-card">
            {owned ? <span className="shop-card-owned">{product.price ? t('Bought', 'Comprado') : t('Received', 'Recebido')}</span> : null}
            <ProductArt product={product} />
            <span className="shop-card-name">{pick(locale, product.name)}</span>
            <Price product={product} className="shop-card-price" />
        </Link>
    );
}

/**
 * The strip above the store: its tabs and the quick search. The grid filters as you type (query and onQuery); on a
 * product's page, Enter goes back to the grid with the search applied.
 */
export function ShopBar({ query, onQuery }: { query?: string; onQuery?: (query: string) => void }) {
    const t = useT();
    const { component } = usePage();
    const [typed, setTyped] = useState('');
    const value = query ?? typed;

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (!onQuery && typed.trim()) {
            router.visit(`/store?q=${encodeURIComponent(typed.trim())}`);
        }
    };

    return (
        <div id="store" className="shop-bar">
            <div className="shop-bar-inner">
                <nav className="shop-tabs" aria-label={t('Store', 'Loja')}>
                    <Link href="/store" className="shop-tab" aria-current={component.startsWith('store/') ? 'page' : undefined}>
                        {t('Store', 'Loja')}
                    </Link>
                </nav>
                <form className="shop-search" role="search" onSubmit={submit}>
                    <input
                        type="search"
                        value={value}
                        placeholder={t('Quick search', 'Busca rápida')}
                        aria-label={t('Search the Store', 'Buscar na Loja')}
                        maxLength={60}
                        onChange={(e) => (onQuery ? onQuery(e.target.value) : setTyped(e.target.value))}
                    />
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M21 21l-4.5-4.5" />
                    </svg>
                </form>
            </div>
        </div>
    );
}
