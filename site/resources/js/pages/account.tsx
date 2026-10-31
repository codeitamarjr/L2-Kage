import { Form, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Notice } from '@/components/auth-card';
import { Divider, Embers, HeroVideo } from '@/components/decor';
import { OrdersList } from '@/components/orders';
import { ItemIcon } from '@/components/item-icon';
import { languageTag, raceName, useLocale, useT, type Locale } from '@/i18n';
import type { CharacterDetail, CharacterItem, CharacterSummary, Order, SharedProps } from '@/types';

type Props = {
    account: { login: string; gm: boolean; created: string | null; email: string | null } | null;
    characters?: CharacterSummary[];
    character?: CharacterDetail | null;
    orders?: Order[];
    tab: 'characters' | 'orders' | 'security';
};

type ItemsTab = 'equipped' | 'inventory' | 'warehouse';

// The page's language for the formatting helpers below; set by Account (the page component) on every render, before its children.
let locale: Locale = 'en';
const fmt = (n: number) => n.toLocaleString(languageTag(locale));
const date = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(languageTag(locale), { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const duration = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);

    return h ? `${fmt(h)}h ${m}m` : `${m}m`;
};

/** Equipment slots and item types as the server names them (English) -> Portuguese. */
const SLOT_PT: Record<string, string> = {
    Underwear: 'Roupa de baixo', Head: 'Cabeça', Hair: 'Cabelo', 'Hair 2': 'Cabelo 2', Necklace: 'Colar', Weapon: 'Arma', Chest: 'Torso',
    Shield: 'Escudo', 'Right Earring': 'Brinco direito', 'Left Earring': 'Brinco esquerdo', Gloves: 'Luvas', Legs: 'Pernas', Feet: 'Pés',
    'Right Ring': 'Anel direito', 'Left Ring': 'Anel esquerdo', 'Left Bracelet': 'Bracelete esquerdo', 'Right Bracelet': 'Bracelete direito',
    Cloak: 'Capa', Belt: 'Cinto', Brooch: 'Broche', 'Artifact Book': 'Livro de Artefatos', Artifact: 'Artefato', Armor: 'Armadura', Item: 'Item',
};
const slotName = (name: string) => (locale === 'pt_BR' ? (SLOT_PT[name] ?? name.replace('Talisman', 'Talismã').replace('Brooch Jewel', 'Joia do broche')) : name);

/** The message for a status flashed after a redirect (register, reset, change password / email). */
function FlashNotice() {
    const { flash } = usePage<SharedProps>().props;
    const t = useT();
    const messages: Record<string, [string, 'info' | 'error']> = {
        'account-created': [t('Your account is ready! Log in to the game with your account name and password.', 'Sua conta está pronta! Entre no jogo com seu nome de conta e senha.'), 'info'],
        'password-reset': [
            t(
                `New password saved${flash.login ? ` for ${flash.login}` : ''}. Sign in with it here and in the game.`,
                `Nova senha salva${flash.login ? ` para ${flash.login}` : ''}. Use-a para entrar aqui e no jogo.`,
            ),
            'info',
        ],
        'order-placed': [
            t(
                'Order placed! The items arrive by in-game mail within seconds.',
                'Pedido realizado! Os itens chegam pelo correio do jogo em segundos.',
            ),
            'info',
        ],
        'gift-opened': [
            t(
                'Gift box opened! It arrives by in-game mail within seconds.',
                'Caixa de presente aberta! Ela chega pelo correio do jogo em segundos.',
            ),
            'info',
        ],
        'password-changed': [t('Password changed. Use the new one the next time you log in to the game.', 'Senha alterada. Use a nova na próxima vez que entrar no jogo.'), 'info'],
        'email-sent': [
            t(
                `We sent a confirmation link to ${flash.sentTo ?? 'your new address'}. Your email changes once you click it.`,
                `Enviamos um link de confirmação para ${flash.sentTo ?? 'seu novo endereço'}. Seu e-mail muda assim que você clicar nele.`,
            ),
            'info',
        ],
        'email-confirmed': [t('Email confirmed. Password reset links will be sent there.', 'E-mail confirmado. Os links de redefinição de senha serão enviados para ele.'), 'info'],
        'link-invalid': [t('That link has expired or was already used.', 'Esse link expirou ou já foi usado.'), 'error'],
    };
    const message = flash.status ? messages[flash.status] : undefined;

    return message ? <Notice tone={message[1]}>{message[0]}</Notice> : null;
}

function SignIn() {
    const [secure, setSecure] = useState(true);
    const t = useT();
    useEffect(() => setSecure(window.location.protocol === 'https:'), []);

    return (
        <section className="account-section">
            <Form action="/account/login" method="post" className="signin-card" resetOnError={['password']}>
                {({ errors, processing }) => (
                    <>
                        <h2 className="signin-title text-gold">{t('Sign In', 'Entrar')}</h2>
                        <FlashNotice />
                        <label className="field">
                            <span className="field-label">{t('Account name', 'Nome da conta')}</span>
                            <input name="login" autoComplete="username" required maxLength={45} spellCheck={false} autoCapitalize="none" autoFocus />
                        </label>
                        <label className="field">
                            <span className="field-label">{t('Password', 'Senha')}</span>
                            <input name="password" type="password" autoComplete="current-password" required maxLength={64} />
                        </label>
                        {errors.login || errors.password ? (
                            <p className="form-error" role="alert">
                                {errors.login ?? errors.password}
                            </p>
                        ) : null}
                        <button className="btn btn-primary signin-submit" type="submit" disabled={processing}>
                            {t('Enter Aden', 'Entrar em Aden')}
                        </button>
                        <p className="signin-note">
                            {t('Use the same account and password as in the game.', 'Use a mesma conta e senha do jogo.')}{' '}
                            <Link href="/forgot-password" className="text-link">
                                {t('Forgot your password?', 'Esqueceu sua senha?')}
                            </Link>
                        </p>
                        <p className="signin-note">
                            {t('No account yet?', 'Ainda não tem conta?')}{' '}
                            <Link href="/register" className="text-link">
                                {t('Create one', 'Crie uma')}
                            </Link>
                        </p>
                        {secure ? null : (
                            <p className="signin-note signin-insecure">
                                {t(
                                    'This connection is not encrypted (http). Only sign in on a network you trust.',
                                    'Esta conexão não é criptografada (http). Só entre em uma rede de confiança.',
                                )}
                            </p>
                        )}
                    </>
                )}
            </Form>
        </section>
    );
}

function Bar({ name, label, current, max, text }: { name: string; label: string; current: number; max: number; text?: string }) {
    const pct = max ? Math.max(0, Math.min(100, (current / max) * 100)) : 0;

    return (
        <div className={`bar bar-${name}`}>
            <span className="bar-label">{label}</span>
            <span className="bar-track">
                <span className="bar-fill" style={{ width: `${pct}%` }}></span>
            </span>
            <span className="bar-value">{text ?? `${fmt(current)} / ${fmt(max)}`}</span>
        </div>
    );
}

function ItemsTable({ character }: { character: CharacterDetail }) {
    const [tab, setTab] = useState<ItemsTab>('equipped');
    const [search, setSearch] = useState('');
    const t = useT();
    const tabNames: Record<ItemsTab, string> = { equipped: t('Equipped', 'Equipado'), inventory: t('Inventory', 'Inventário'), warehouse: t('Warehouse', 'Armazém') };
    const term = search.trim().toLowerCase();
    const rows: CharacterItem[] = character.items[tab].filter((item) => !term || item.name.toLowerCase().includes(term));

    return (
        <>
            <div className="items-tabs" role="tablist">
                {(['equipped', 'inventory', 'warehouse'] as const).map((key) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        className={`items-tab${tab === key ? ' is-active' : ''}`}
                        onClick={() => setTab(key)}
                    >
                        {tabNames[key]} ({character.items[key].length})
                    </button>
                ))}
            </div>
            <input className="items-search" type="search" placeholder={t('Search items…', 'Buscar itens…')} aria-label={t('Search items', 'Buscar itens')} value={search} onChange={(e) => setSearch(e.target.value)} />
            <div className="items-table-wrap">
                <table className="items-table">
                    <thead>
                        <tr>
                            <th>{tab === 'equipped' ? t('Slot', 'Espaço') : t('Type', 'Tipo')}</th>
                            <th>{t('Item', 'Item')}</th>
                            <th>{t('Grade', 'Grau')}</th>
                            <th className="num">{t('Count', 'Qtd.')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? (
                            rows.map((item) => (
                                // The whole row opens the item; the name stays a real link for keyboards and new tabs.
                                <tr
                                    key={item.id}
                                    className="item-row-link"
                                    onClick={(e) => {
                                        if (!(e.target as HTMLElement).closest('a')) {
                                            router.visit(`/items/${item.itemId}`);
                                        }
                                    }}
                                >
                                    <td className="item-slot">{slotName((tab === 'equipped' ? item.slot : item.type === 'EtcItem' ? 'Item' : item.type) ?? '')}</td>
                                    <td className={`item-name item-${item.type.toLowerCase()}`}>
                                        <Link href={`/items/${item.itemId}`} className="item-name-link">
                                            <ItemIcon item={item} size="sm" />
                                            <span>
                                                {item.enchant ? <span className="item-enchant">+{item.enchant} </span> : null}
                                                {item.name}
                                            </span>
                                        </Link>
                                    </td>
                                    <td>{item.grade ? <span className={`grade grade-${item.grade.toLowerCase()}`}>{item.grade}</span> : null}</td>
                                    <td className="num">{fmt(item.count)}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td className="about-empty" colSpan={4}>
                                    {term ? t('No items match your search.', 'Nenhum item corresponde à busca.') : t('Nothing here yet.', 'Nada aqui ainda.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}

function CharacterPanel({ character: c }: { character: CharacterDetail }) {
    const s = c.status;
    const t = useT();
    const sex = c.sex === 'Female' ? t('Female', 'Feminino') : t('Male', 'Masculino');
    const subtitle = [
        c.title ? `“${c.title}”` : null,
        t(`${sex} ${c.race}`, `${raceName(locale, c.race)} ${sex.toLowerCase()}`),
        c.clan ? `${t('Clan', 'Clã')} ${c.clan}${c.clanLevel !== null ? ` (Lv ${c.clanLevel})` : ''}` : t('No clan', 'Sem clã'),
        c.noblesse ? 'Noblesse' : null,
        c.gm ? 'Game Master' : null,
    ].filter(Boolean);
    const stats: [string, string][] = [
        [t('Skill Points', 'Pontos de Habilidade'), fmt(s.sp)],
        ['Adena', fmt(c.adena)],
        ['PvP', fmt(s.pvp)],
        ['PK', fmt(s.pk)],
        [t('Reputation', 'Reputação'), fmt(s.karma)],
        [t('Fame', 'Fama'), fmt(s.fame)],
        [t('Vitality', 'Vitalidade'), fmt(s.vitality)],
        [t('Time Played', 'Tempo de Jogo'), duration(s.onlineSeconds)],
        [t('Created', 'Criado em'), date(s.created)],
        [t('Last Seen', 'Visto por Último'), c.online ? t('Online now', 'Online agora') : date(c.lastSeen)],
    ];

    return (
        <div className="char-detail">
            <div className="char-head">
                <div>
                    <p className="char-class">
                        {c.className}
                        {c.baseClassName ? ` (${t('base', 'base')}: ${c.baseClassName})` : ''}
                    </p>
                    <h2 className="char-name text-gold">{c.name}</h2>
                    <p className="char-sub">{subtitle.join(' · ')}</p>
                </div>
                <div className="char-level">
                    <span className="char-level-num text-gold">{c.level}</span>
                    <span className="char-level-label">{t('Level', 'Nível')}</span>
                </div>
            </div>

            <div className="bars">
                <Bar name="cp" label="CP" current={s.cp.current} max={s.cp.max} />
                <Bar name="hp" label="HP" current={s.hp.current} max={s.hp.max} />
                <Bar name="mp" label="MP" current={s.mp.current} max={s.mp.max} />
                <Bar name="xp" label="XP" current={s.expPercent} max={100} text={`${s.expPercent.toFixed(2)}%`} />
            </div>

            <dl className="char-stats">
                {stats.map(([label, value]) => (
                    <div key={label} className="char-stat">
                        <dt>{label}</dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>

            <ItemsTable key={c.id} character={c} />
            <p className="about-footnote">
                {t(
                    'Data is what the game server last saved: on logout and every few minutes while you play.',
                    'Os dados são os últimos salvos pelo servidor do jogo: ao sair e a cada poucos minutos enquanto você joga.',
                )}
            </p>
        </div>
    );
}

/** The Security tab: account details, then the recovery email (confirmed by a link) and password forms. */
function SecurityTab({ account }: { account: NonNullable<Props['account']> }) {
    const { emailReady } = usePage<SharedProps>().props;
    const email = account.email;
    const t = useT();
    const recoveryLabel = t('Recovery email', 'E-mail de recuperação');
    const details: [string, string][] = [
        [t('Account name', 'Nome da conta'), account.login],
        [t('Created', 'Criada em'), date(account.created)],
        [recoveryLabel, email ?? t('None', 'Nenhum')],
        [t('Access', 'Acesso'), account.gm ? 'Game Master' : t('Player', 'Jogador')],
    ];

    return (
        <section className="security-panel">
            <dl className="security-details">
                {details.map(([label, value]) => (
                    <div key={label}>
                        <dt>{label}</dt>
                        <dd className={label === recoveryLabel && !email ? 'is-missing' : undefined}>{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="security-grid">
                <Form action="/account/email" method="post" className="security-card" resetOnSuccess resetOnError={['email_password']} options={{ preserveScroll: true }}>
                    {({ errors, processing }) => (
                        <>
                            <h3>{recoveryLabel}</h3>
                            <p className="security-current">
                                {email ? (
                                    <>
                                        {t('Currently', 'Atualmente')} <strong>{email}</strong>
                                    </>
                                ) : (
                                    t('None yet: add one to be able to reset your password.', 'Nenhum ainda: adicione um para poder redefinir sua senha.')
                                )}
                            </p>
                            <label className="field">
                                <span className="field-label">{email ? t('New email', 'Novo e-mail') : t('Email', 'E-mail')}</span>
                                <input name="email" type="email" autoComplete="email" required maxLength={255} />
                            </label>
                            {errors.email ? <Notice tone="error">{errors.email}</Notice> : null}
                            <label className="field">
                                <span className="field-label">{t('Current password', 'Senha atual')}</span>
                                <input name="email_password" type="password" autoComplete="current-password" required maxLength={64} />
                            </label>
                            {errors.email_password ? <Notice tone="error">{errors.email_password}</Notice> : null}
                            {emailReady ? null : <p className="security-current">{t('Adding an email opens very soon.', 'A opção de adicionar e-mail abre em breve.')}</p>}
                            <button className="btn btn-outline btn-small" type="submit" disabled={processing || !emailReady}>
                                {email ? t('Change Email', 'Alterar E-mail') : t('Add Email', 'Adicionar E-mail')}
                            </button>
                        </>
                    )}
                </Form>
                <Form action="/account/password" method="post" className="security-card" resetOnSuccess resetOnError options={{ preserveScroll: true }}>
                    {({ errors, processing }) => (
                        <>
                            <h3>{t('Password', 'Senha')}</h3>
                            <p className="security-current">{t('The same password logs you in here and in the game.', 'A mesma senha vale aqui e no jogo.')}</p>
                            <label className="field">
                                <span className="field-label">{t('Current password', 'Senha atual')}</span>
                                <input name="current_password" type="password" autoComplete="current-password" required maxLength={64} />
                            </label>
                            {errors.current_password ? <Notice tone="error">{errors.current_password}</Notice> : null}
                            <label className="field">
                                <span className="field-label">{t('New password', 'Nova senha')}</span>
                                <input name="password" type="password" autoComplete="new-password" required minLength={6} maxLength={16} />
                            </label>
                            <label className="field">
                                <span className="field-label">{t('Repeat new password', 'Repita a nova senha')}</span>
                                <input name="password_confirmation" type="password" autoComplete="new-password" required minLength={6} maxLength={16} />
                            </label>
                            {errors.password ? <Notice tone="error">{errors.password}</Notice> : null}
                            <button className="btn btn-outline btn-small" type="submit" disabled={processing}>
                                {t('Change Password', 'Alterar Senha')}
                            </button>
                        </>
                    )}
                </Form>
            </div>
            <ul className="security-tips">
                <li>{t('The staff will never ask for your password: not in game, not on Discord, not by email.', 'A equipe nunca vai pedir sua senha: nem no jogo, nem no Discord, nem por e-mail.')}</li>
                <li>
                    {t(
                        "Use a password you don't use anywhere else. The game allows up to 16 characters, so use them all.",
                        'Use uma senha que você não usa em nenhum outro lugar. O jogo aceita até 16 caracteres, então use todos.',
                    )}
                </li>
                <li>
                    {t(
                        <>Forgot it? Use <Link href="/forgot-password" className="text-link">Forgot your password</Link> on the sign-in page: the link goes to your recovery email.</>,
                        <>Esqueceu? Use <Link href="/forgot-password" className="text-link">Esqueceu sua senha</Link> na página de login: o link vai para seu e-mail de recuperação.</>,
                    )}
                </li>
            </ul>
        </section>
    );
}

/** Premium accounts: Einhasad's blessing, until when it lasts and what it grants. */
function PremiumNotice({ premium }: { premium: { until: string; bonuses: string } }) {
    const t = useT();
    const until = new Date(premium.until).toLocaleDateString(languageTag(locale), { day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <aside className="premium-notice" aria-label={t('Premium account', 'Conta premium')}>
            <span className="premium-notice-star" aria-hidden="true">
                ★
            </span>
            <div>
                <p className="premium-notice-title">
                    {t('The blessing of Einhasad is upon your account', 'A bênção de Einhasad está sobre a sua conta')}
                </p>
                <p className="premium-notice-text">
                    {t(`Premium until ${until}: ${premium.bonuses}, for every character.`, `Premium até ${until}: ${premium.bonuses}, para todos os personagens.`)}
                </p>
            </div>
        </aside>
    );
}

export default function Account({ account, characters = [], character, orders, tab }: Props) {
    const { emailReady, player } = usePage<SharedProps>().props;
    const t = useT();
    locale = useLocale();

    return (
        <>
            <section className="lore-hero account-hero">
                <HeroVideo src="/assets/video/account-hero.mp4?v=3" poster="/assets/video/account-hero-poster.jpg" />
                <div className="lore-hero-shade"></div>
                <Embers />
                <div className="lore-hero-content">
                    <p className="hero-kicker">{t('Player Area', 'Área do Jogador')}</p>
                    <h1 className="lore-title text-gold">{account ? `${t('Welcome', 'Bem-vindo')}, ${account.login}` : t('Your Account', 'Sua Conta')}</h1>
                    <Divider />
                    <p className="hero-tagline">
                        {!account
                            ? t(
                                  'Sign in with your game account to see your characters, their status and items.',
                                  'Entre com sua conta do jogo para ver seus personagens, seus status e itens.',
                              )
                            : tab === 'security'
                              ? t('Keep your account safe: recovery email and password.', 'Mantenha sua conta segura: e-mail de recuperação e senha.')
                              : tab === 'orders'
                                ? t('Everything you claimed or bought in the Treasury.', 'Tudo o que você resgatou ou comprou no Tesouro.')
                                : t('Your heroes of Aden.', 'Seus heróis de Aden.')}
                    </p>
                </div>
            </section>

            {account === null ? (
                <SignIn />
            ) : (
                <section className="account-section">
                    <div className="container">
                        <div className="account-bar">
                            <p>
                                {t('Signed in as', 'Conectado como')} <strong>{account.login}</strong>
                                {account.gm ? <span className="gm-badge">GM</span> : null}
                                {player?.premium ? <span className="premium-badge">★ Premium</span> : null}
                            </p>
                            <Link href="/account/logout" method="post" as="button" className="btn btn-outline btn-small">
                                {t('Sign Out', 'Sair')}
                            </Link>
                        </div>

                        <nav className="account-tabs" aria-label={t('Player area', 'Área do jogador')}>
                            <Link href="/account" className="account-tab" aria-current={tab === 'characters' ? 'page' : undefined} preserveScroll>
                                {t('Characters', 'Personagens')}
                            </Link>
                            <Link href="/account/orders" className="account-tab" aria-current={tab === 'orders' ? 'page' : undefined} preserveScroll>
                                {t('Orders', 'Pedidos')}
                            </Link>
                            <Link href="/account/security" className="account-tab" aria-current={tab === 'security' ? 'page' : undefined} preserveScroll>
                                {t('Security', 'Segurança')}
                                {account.email || !emailReady ? null : <span className="account-tab-alert" aria-label={t('needs attention', 'precisa de atenção')}></span>}
                            </Link>
                        </nav>

                        {player?.premium ? <PremiumNotice premium={player.premium} /> : null}

                        <div className="account-notices">
                            <FlashNotice />
                            {account.email || !emailReady || tab === 'security' ? null : (
                                <Notice tone="error">
                                    {t(
                                        "Your account has no recovery email, so a forgotten password can't be reset.",
                                        'Sua conta não tem e-mail de recuperação, então uma senha esquecida não pode ser redefinida.',
                                    )}{' '}
                                    <Link href="/account/security" className="text-link">
                                        {t('Add one in Security', 'Adicione um em Segurança')}
                                    </Link>
                                    .
                                </Notice>
                            )}
                        </div>

                        {tab === 'security' ? <SecurityTab account={account} /> : null}

                        {tab === 'orders' && orders ? <OrdersList orders={orders} /> : null}

                        {tab !== 'characters' ? null : (
                            <>
                                <h2 className="account-heading">{t('Characters', 'Personagens')}</h2>
                                {characters.length ? (
                                    <div className="char-list" role="list">
                                        {characters.map((c) => (
                                            <Link
                                                key={c.id}
                                                href={`/account?character=${c.id}`}
                                                only={['character']}
                                                preserveScroll
                                                preserveState
                                                role="listitem"
                                                className={`char-card${character?.id === c.id ? ' is-selected' : ''}`}
                                            >
                                                <span className="char-card-top">
                                                    <span className={`char-dot${c.online ? ' is-online' : ''}`}></span>
                                                    <span className="char-card-state">{c.online ? 'Online' : `${t('Last seen', 'Visto em')} ${date(c.lastSeen)}`}</span>
                                                </span>
                                                <span className="char-card-name">{c.name}</span>
                                                <span className="char-card-class">
                                                    {c.className} · Lv {c.level}
                                                </span>
                                                <span className="char-card-meta">
                                                    {[raceName(locale, c.race), c.clan ? `${t('Clan', 'Clã')} ${c.clan}` : null, c.noblesse ? 'Noblesse' : null].filter(Boolean).join(' · ')}
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="about-empty">{t('No characters yet. Create one in the game and it will show up here.', 'Nenhum personagem ainda. Crie um no jogo e ele aparecerá aqui.')}</p>
                                )}

                                {character ? <CharacterPanel character={character} /> : null}
                            </>
                        )}
                    </div>
                </section>
            )}
        </>
    );
}
