import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { span } from '@/components/happening';
import { ProductArt, Price, ShopBar } from '@/components/store';
import { useNow } from '@/hooks/use-page-effects';
import { languageTag, pick, useLocale, useT } from '@/i18n';
import type { StoreBuyer, StoreProduct } from '@/types';

type Props = {
    product: StoreProduct;
    /** Signed in: the account's characters and whether it already holds this; null for guests. */
    buyer: StoreBuyer | null;
};

type Tab = 'information' | 'details' | 'after';

/** "80 days 13:53:20" until the offer closes. */
function Countdown({ until }: { until: string }) {
    const now = useNow(1000);
    const t = useT();
    const left = Math.max(0, Math.floor((Date.parse(until) - now) / 1000));
    const clock = [Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60].map((n) => String(n).padStart(2, '0')).join(':');

    return (
        <p className="shop-countdown">
            {t(`${Math.floor(left / 86400)} days`, `${Math.floor(left / 86400)} dias`)} <span>{clock}</span>
        </p>
    );
}

/** What's in the product: its groups of items, each linking to the item database. */
function Contents({ product }: { product: StoreProduct }) {
    const locale = useLocale();
    const t = useT();
    const fmt = (n: number) => n.toLocaleString(languageTag(locale));

    return (
        <>
            {product.contents.map((group) => (
                <section key={group.title.en} className="shop-section">
                    <h2>{pick(locale, group.title)}</h2>
                    <ul className="shop-items">
                        {group.items.map((item, i) => {
                            const label = (
                                <>
                                    {item.enchant ? <span className="item-enchant">+{item.enchant}</span> : null} {item.name}
                                </>
                            );

                            return (
                                <li key={`${item.name}-${i}`}>
                                    {item.id ? <Link href={`/items/${item.id}`}>{label}</Link> : <span>{label}</span>}
                                    {item.count && item.count > 1 ? <span className="shop-item-count">× {fmt(item.count)}</span> : null}
                                    {item.chance !== undefined ? <span className="shop-item-chance">{item.chance}%</span> : null}
                                </li>
                            );
                        })}
                    </ul>
                    {group.note ? <p className="shop-note">{pick(locale, group.note)}</p> : null}
                </section>
            ))}
            {product.hunt ? (
                <section className="shop-section">
                    <h2>{t('The first steps', 'As primeiras etapas')}</h2>
                    <div className="shop-table-wrap">
                        <table className="shop-table">
                            <thead>
                                <tr>
                                    <th>{t('Step', 'Etapa')}</th>
                                    <th>{t('Free', 'Grátis')}</th>
                                    <th>Premium</th>
                                </tr>
                            </thead>
                            <tbody>
                                {product.hunt.firstSteps.map((step, i) => (
                                    <tr key={i}>
                                        <td>{i + 1}</td>
                                        <td>
                                            {fmt(step.free.count)} × {step.free.name}
                                        </td>
                                        <td>
                                            {fmt(step.premium.count)} × {step.premium.name}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="shop-note">
                        {t(
                            `Open the Hunt Pass window in the game to see all ${fmt(product.hunt.steps)} steps.`,
                            `Abra a janela do Hunt Pass no jogo para ver todas as ${fmt(product.hunt.steps)} etapas.`,
                        )}
                    </p>
                </section>
            ) : null}
        </>
    );
}

/**
 * The panel beside the product: its price, the character that pays and receives it (only those that meet the
 * requirement can be picked), how many, and Buy. The server checks all of this again, and the game server checks the
 * price once more when it charges, because the site only knows the character's last save.
 */
function PurchasePanel({ product, buyer }: Props) {
    const locale = useLocale();
    const t = useT();
    const tag = languageTag(locale);
    const fmt = (n: number) => n.toLocaleString(tag);
    const [characterId, setCharacterId] = useState('');
    const [quantity, setQuantity] = useState(1);
    const price = product.price;
    const total = price ? price.count * quantity : 0;
    const characters = buyer?.characters ?? [];
    const meetsLevel = (level: number) => level >= (product.minLevel ?? 0);
    const canPay = (balance: number | null) => balance === null || balance >= total;
    const selected = characters.find((c) => String(c.id) === characterId);
    const selectable = selected && meetsLevel(selected.level) && canPay(selected.balance);
    const buyLabel = price ? t(`Buy for ${fmt(total)} ${price.name}`, `Comprar por ${fmt(total)} ${price.name}`) : t('Get it for free', 'Receber grátis');

    const limit = product.limit ? (
        <p className="shop-limit">
            {t('Limit per account', 'Limite por conta')}: <strong>{pick(locale, product.limit)}</strong>
        </p>
    ) : null;

    let body;

    if (product.kind === 'hunt') {
        body = (
            <p className="shop-panel-note">
                {product.open
                    ? t('This one is unlocked in the game, in the Hunt Pass window.', 'Este é desbloqueado no jogo, na janela do Hunt Pass.')
                    : t('The Hunt Pass is off on this server for now.', 'O Hunt Pass está desligado neste servidor por enquanto.')}
            </p>
        );
    } else if (buyer?.owned) {
        body = (
            <>
                {limit}
                <p className="shop-panel-owned">
                    {product.kind === 'gift'
                        ? t(`Opened for ${buyer.owned.characterName} this week.`, `Aberta para ${buyer.owned.characterName} nesta semana.`)
                        : t(`Bought for ${buyer.owned.characterName} this season.`, `Comprado para ${buyer.owned.characterName} nesta temporada.`)}
                </p>
                {buyer.owned.nextAt ? <NextIn at={buyer.owned.nextAt} /> : null}
                <Link href="/account/orders" className="shop-buy is-outline">
                    {t('See your orders', 'Ver seus pedidos')}
                </Link>
            </>
        );
    } else if (!product.open) {
        body = <p className="shop-panel-note">{t('Closed for now. Check back soon.', 'Fechado por enquanto. Volte em breve.')}</p>;
    } else {
        body = (
            <Form action={`/treasury/${product.slug}`} method="post" className="shop-form" options={{ preserveScroll: true }}>
                {({ errors, processing }) => (
                    <>
                        <label className="shop-field">
                            <span>{t('Character', 'Personagem')}</span>
                            <select name="character_id" value={characterId} disabled={!buyer || characters.length === 0} onChange={(e) => setCharacterId(e.target.value)}>
                                <option value="">{t('Choose character', 'Escolha o personagem')}</option>
                                {characters.map((c) => (
                                    <option key={c.id} value={c.id} disabled={!meetsLevel(c.level) || !canPay(c.balance)}>
                                        {c.name} · Lv {c.level}
                                        {price ? ` · ${fmt(c.balance ?? 0)} ${price.name}` : ''}
                                    </option>
                                ))}
                            </select>
                        </label>

                        {buyer && selected && price ? (
                            <p className="shop-balance">
                                {t('In the bag', 'Na bolsa')}: <strong>{fmt(selected.balance ?? 0)} {price.name}</strong>
                            </p>
                        ) : null}

                        {product.maxQuantity > 1 ? (
                            <div className="shop-stepper" role="group" aria-label={t('Quantity', 'Quantidade')}>
                                <button type="button" disabled={quantity <= 1} aria-label={t('One less', 'Um a menos')} onClick={() => setQuantity(quantity - 1)}>
                                    −
                                </button>
                                <output>{quantity}</output>
                                <input type="hidden" name="quantity" value={quantity} />
                                <button type="button" disabled={quantity >= product.maxQuantity} aria-label={t('One more', 'Um a mais')} onClick={() => setQuantity(quantity + 1)}>
                                    +
                                </button>
                            </div>
                        ) : null}
                        {limit}

                        {errors.character_id || errors.package || errors.quantity ? (
                            <p className="form-error" role="alert">
                                {errors.character_id ?? errors.package ?? errors.quantity}
                            </p>
                        ) : null}

                        <button type="submit" className="shop-buy" disabled={!buyer || !selectable || processing}>
                            {buyLabel}
                        </button>

                        {!buyer ? (
                            <p className="shop-panel-note">
                                {price ? t('To buy this you need to', 'Para comprar, você precisa') : t('To get this you need to', 'Para receber, você precisa')}{' '}
                                <Link href={`/account?return=/treasury/${product.slug}`}>{t('log in', 'entrar')}</Link>
                            </p>
                        ) : characters.length === 0 ? (
                            <p className="shop-panel-note">{t('Create a character in the game first.', 'Crie um personagem no jogo primeiro.')}</p>
                        ) : !characters.some((c) => meetsLevel(c.level) && canPay(c.balance)) ? (
                            <p className="shop-panel-note">
                                {price
                                    ? t(`None of your characters has ${fmt(total)} ${price.name} in the bag yet.`, `Nenhum dos seus personagens tem ${fmt(total)} ${price.name} na bolsa ainda.`)
                                    : t(`Reach level ${product.minLevel} with any character to unlock this.`, `Chegue ao nível ${product.minLevel} com qualquer personagem para liberar isto.`)}
                            </p>
                        ) : price ? (
                            <p className="shop-panel-note">
                                {t(
                                    'It’s paid from the chosen character’s bag and arrives in its in-game mailbox. The site shows the last save: every 5 minutes while you play, and when you log out.',
                                    'É pago da bolsa do personagem escolhido e chega na caixa de correio dele no jogo. O site mostra o último salvamento: a cada 5 minutos enquanto você joga, e ao sair.',
                                )}
                            </p>
                        ) : (
                            <p className="shop-panel-note">{t('It arrives in the chosen character’s in-game mailbox.', 'Chega na caixa de correio do personagem escolhido no jogo.')}</p>
                        )}
                    </>
                )}
            </Form>
        );
    }

    return (
        <aside className="shop-panel" aria-label={t('Purchase', 'Compra')}>
            <Price product={{ price: price ? { ...price, count: total } : null }} className="shop-panel-price" />
            {product.endsAt && product.open && !buyer?.owned ? <Countdown until={product.endsAt} /> : null}
            {body}
        </aside>
    );
}

function NextIn({ at }: { at: string }) {
    const now = useNow(30_000);
    const t = useT();

    return (
        <p className="shop-panel-note">
            {t('Next box in', 'Próxima caixa em')} {span(Date.parse(at) - now)}
        </p>
    );
}

/** A product's own page: what it is, what's inside and what to do after buying it, beside the purchase panel. */
export default function TreasuryShow({ product, buyer }: Props) {
    const locale = useLocale();
    const t = useT();
    const [tab, setTab] = useState<Tab>('information');
    const tabs: [Tab, string][] = [
        ['information', t('Information', 'Informações')],
        ['details', t('Product details', 'Detalhes do produto')],
        ['after', t('What to do after purchase', 'O que fazer após a compra')],
    ];

    return (
        <div className="shop is-plain">
            <ShopBar />
            <div className="shop-product">
                <div className="shop-product-main">
                    <header className="shop-product-head">
                        <Link href={`/treasury?category=${product.category}#store`} preserveScroll className="shop-back">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 12H5m6-6l-6 6 6 6" />
                            </svg>
                            {t('Back', 'Voltar')}
                        </Link>
                        <h1 className="text-gold">{pick(locale, product.name)}</h1>
                    </header>

                    <div className="shop-product-tabs" role="tablist">
                        {tabs.map(([key, label]) => (
                            <button key={key} type="button" role="tab" id={`tab-${key}`} aria-selected={tab === key} aria-controls="shop-tab-panel" onClick={() => setTab(key)}>
                                {label}
                            </button>
                        ))}
                    </div>

                    <div id="shop-tab-panel" role="tabpanel" aria-labelledby={`tab-${tab}`}>
                        {tab === 'information' ? (
                            <>
                                <div className="shop-product-art">
                                    <ProductArt product={product} />
                                </div>
                                <section className="shop-section">
                                    <h2>{t('Description', 'Descrição')}</h2>
                                    {product.description.map((paragraph) => (
                                        <p key={paragraph.en}>{pick(locale, paragraph)}</p>
                                    ))}
                                </section>
                            </>
                        ) : null}
                        {tab === 'details' ? <Contents product={product} /> : null}
                        {tab === 'after' ? (
                            <section className="shop-section">
                                <h2>{t('What to do after purchase', 'O que fazer após a compra')}</h2>
                                <ol className="shop-steps">
                                    {product.steps.map((step) => (
                                        <li key={step.en}>{pick(locale, step)}</li>
                                    ))}
                                </ol>
                                {product.kind !== 'hunt' ? (
                                    <p>
                                        {t('Every purchase shows up in', 'Toda compra aparece em')}{' '}
                                        <Link href="/account/orders">{t('your orders', 'seus pedidos')}</Link>
                                        {t(', with its delivery status.', ', com a situação da entrega.')}
                                    </p>
                                ) : null}
                            </section>
                        ) : null}
                    </div>
                </div>
                <PurchasePanel product={product} buyer={buyer} />
            </div>
        </div>
    );
}
