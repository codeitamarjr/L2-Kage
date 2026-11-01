import { Form, Link, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { languageTag, pick, useLocale, useT } from '@/i18n';
import type { Order, OrderRecipient } from '@/types';

/** What an admin sent: the player chooses the character that receives it. */
function Redeem({ order, recipients }: { order: Order; recipients: OrderRecipient[] }) {
    const t = useT();
    const [characterId, setCharacterId] = useState('');

    if (recipients.length === 0) {
        return <p className="order-redeem-note">{t('Create a character in the game first.', 'Crie um personagem no jogo primeiro.')}</p>;
    }

    return (
        <Form action={`/account/orders/${order.id}/redeem`} method="post" className="order-redeem" options={{ preserveScroll: true }}>
            {({ errors, processing }) => (
                <>
                    <select name="character_id" value={characterId} aria-label={t('Character', 'Personagem')} onChange={(e) => setCharacterId(e.target.value)}>
                        <option value="">{t('Choose character', 'Escolha o personagem')}</option>
                        {recipients.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} · Lv {c.level}
                            </option>
                        ))}
                    </select>
                    <button type="submit" className="btn btn-primary btn-small" disabled={!characterId || processing}>
                        {t('Send to this character', 'Enviar para este personagem')}
                    </button>
                    {errors.character_id ? (
                        <p className="form-error" role="alert">
                            {errors.character_id}
                        </p>
                    ) : null}
                </>
            )}
        </Form>
    );
}

/**
 * The Orders tab: everything the account bought in the Store or received from the staff, what's waiting for a character
 * first, then newest first, with what was sent and its in-game delivery. While one is on its way the list polls for the result.
 */
export function OrdersList({ orders, recipients }: { orders: Order[]; recipients: OrderRecipient[] }) {
    const locale = useLocale();
    const t = useT();
    const tag = languageTag(locale);
    const fmt = (n: number) => n.toLocaleString(tag);
    const travelling = orders.some((o) => o.status === 'pending' || o.status === 'processing');
    const { start, stop } = usePoll(4_000, { only: ['orders'] }, { autoStart: false });
    // Just bought or opened: show what's inside without asking for a click.
    const [loadedAt] = useState(() => Date.now());
    const isFresh = (order: Order) => loadedAt - Date.parse(order.createdAt) < 120_000;

    useEffect(() => {
        if (travelling) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [travelling, start, stop]);

    const statuses = {
        waiting: t('Choose a character', 'Escolha um personagem'),
        pending: t('On its way', 'A caminho'),
        processing: t('On its way', 'A caminho'),
        done: t('Delivered by mail', 'Entregue pelo correio'),
        failed: t('Failed, nothing taken', 'Falhou, nada cobrado'),
    };

    return (
        <section className="orders" aria-labelledby="orders-title">
            <div className="orders-head">
                <h2 id="orders-title" className="account-heading">
                    {t('Order history', 'Histórico de pedidos')}
                </h2>
                <Link href="/store" className="btn btn-outline btn-small">
                    {t('Visit the Store', 'Visitar a Loja')}
                </Link>
            </div>
            {orders.length === 0 ? (
                <p className="about-empty">
                    {t('No orders yet. Your weekly gift box and everything else is in the ', 'Nenhum pedido ainda. Sua caixa de presente semanal e todo o resto estão na ')}
                    <Link href="/store" className="text-link">
                        {t('Store', 'Loja')}
                    </Link>
                    .
                </p>
            ) : (
                <ol className="orders-list">
                    {orders.map((order) => (
                        <li key={order.id} className={`order is-${order.type}`}>
                            <details open={isFresh(order) || order.status === 'waiting'}>
                                <summary className="order-summary">
                                    <span className="order-kind">{order.fromStaff ? t('From the staff', 'Da equipe') : pick(locale, order.typeName)}</span>
                                    <span className="order-name">{pick(locale, order.name)}</span>
                                    <span className="order-meta">
                                        {new Date(order.createdAt).toLocaleDateString(tag, { day: 'numeric', month: 'short', year: 'numeric' })}
                                        {order.characterName ? ` · ${order.characterName}` : ''}
                                    </span>
                                    <span className="order-price">{order.price ? `${fmt(order.price.count)} ${order.price.currency}` : t('Free', 'Grátis')}</span>
                                    <span className={`order-status is-${order.status}`}>{statuses[order.status]}</span>
                                </summary>
                                {order.note ? <p className="order-note">{order.note}</p> : null}
                                <ul className="pkg-items order-items">
                                    {order.items.map((item, i) => (
                                        <li key={`${item.name}-${i}`}>
                                            {item.enchant ? <span className="item-enchant">+{item.enchant}</span> : null} {item.name}
                                            {item.count > 1 ? <span className="gift-item-count"> × {fmt(item.count)}</span> : null}
                                            {item.bonus ? <span className="gift-bonus-tag">{t('Bonus', 'Bônus')}</span> : null}
                                        </li>
                                    ))}
                                    {order.randomBonus ? <li>{t('Plus one random bonus', 'Mais um bônus aleatório')}</li> : null}
                                </ul>
                                {order.status === 'waiting' ? <Redeem order={order} recipients={recipients} /> : null}
                            </details>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
