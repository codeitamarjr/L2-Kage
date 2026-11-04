import { Link, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Divider } from '@/components/decor';
import PageHero from '@/components/page-hero';
import { HappeningSection } from '@/components/happening';
import { CLIENT_DOWNLOAD_URL, CLIENT_INI_URL, CLIENT_START_URL } from '@/data/links';
import { languageTag, pick, raceName, useLocale, useT, type Localized } from '@/i18n';
import type { EventFeed, ServerStatus } from '@/types';

type Props = {
    status: ServerStatus;
    events?: EventFeed | null;
};

// What kind of server this is. L2J Mobius' licence asks servers to credit its developers and never to distribute world
// content (client files); NCSOFT owns Lineage II, so the server says plainly that it is an unofficial fan project.
const FREE_SERVER: { title: Localized; text: Localized }[] = [
    {
        title: { en: 'Free to Play', pt: 'Gratuito' },
        text: {
            en: 'No subscription and no client fee. Create an account on this website and you are ready to play.',
            pt: 'Sem assinatura e sem taxa pelo cliente. Crie uma conta neste site e você já pode jogar.',
        },
    },
    {
        title: { en: 'Open Source', pt: 'Código Aberto' },
        text: {
            en: 'Runs on L2J Mobius, the open-source Java emulator of Lineage II Essence, built by the Mobius developers in their free time.',
            pt: 'Roda no L2J Mobius, o emulador Java de código aberto do Lineage II Essence, feito pelos desenvolvedores do Mobius no seu tempo livre.',
        },
    },
    {
        title: { en: 'Community Run', pt: 'Feito pela Comunidade' },
        text: {
            en: 'An independent, non-commercial server, hosted by players for players. Play fair and respect each other.',
            pt: 'Um servidor independente e sem fins lucrativos, mantido por jogadores para jogadores. Jogue limpo e respeite os outros.',
        },
    },
    {
        title: { en: 'Unofficial', pt: 'Não Oficial' },
        text: {
            en: 'Not affiliated with or endorsed by NCSOFT. Bring your own Essence client: no game files are distributed here.',
            pt: 'Sem vínculo nem endosso da NCSOFT. Traga seu próprio cliente Essence: nenhum arquivo do jogo é distribuído aqui.',
        },
    },
];

const RATE_CARDS: [keyof ServerStatus['rates'], Localized][] = [
    ['xp', { en: 'Experience', pt: 'Experiência' }],
    ['sp', { en: 'Skill Points', pt: 'Pontos de Habilidade' }],
    ['adena', { en: 'Adena', pt: 'Adena' }],
    ['drop', { en: 'Drop', pt: 'Drop' }],
    ['spoil', { en: 'Spoil', pt: 'Spoil' }],
    ['raid', { en: 'Raid Boss', pt: 'Raid Boss' }],
    ['questXp', { en: 'Quest XP / SP', pt: 'XP / SP de Missões' }],
    ['questAdena', { en: 'Quest Adena', pt: 'Adena de Missões' }],
];

const TOTALS: Record<'accounts' | 'characters' | 'clans', Localized> = {
    accounts: { en: 'accounts', pt: 'contas' },
    characters: { en: 'characters', pt: 'personagens' },
    clans: { en: 'clans', pt: 'clãs' },
};

function StatusCard({ label, up, note }: { label: string; up: boolean; note: string }) {
    return (
        <div className={`status-card ${up ? 'is-online' : 'is-offline'}`}>
            <p className="status-label">{label}</p>
            <p className="status-value">
                <span className="status-dot" aria-hidden="true"></span>
                <span className="status-text">{up ? 'Online' : 'Offline'}</span>
            </p>
            <p className="status-note">{note}</p>
        </div>
    );
}

function Ranking({ title, rows }: { title: string; rows: { name: string; race: string; level: number; value: string }[] }) {
    const locale = useLocale();
    const t = useT();

    return (
        <div className="ranking">
            <h3 className="about-subtitle">{title}</h3>
            <ol className="ranking-list">
                {rows.length ? (
                    rows.map((row) => (
                        <li key={row.name} className="ranking-row">
                            <span className="ranking-name">
                                {row.name}
                                <span className="ranking-meta">
                                    {raceName(locale, row.race)} · Lv {row.level}
                                </span>
                            </span>
                            <span className="ranking-value text-gold">{row.value}</span>
                        </li>
                    ))
                ) : (
                    <li className="about-empty">{t('No heroes yet. Be the first.', 'Nenhum herói ainda. Seja o primeiro.')}</li>
                )}
            </ol>
        </div>
    );
}

/** The game server's clock, which event times and daily resets follow, with the visitor's own time beside it. */
function ServerClock({ timezone }: { timezone: string }) {
    const locale = useLocale();
    const t = useT();
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 1_000);

        return () => window.clearInterval(timer);
    }, []);

    const time = (timeZone?: string) => now.toLocaleTimeString(languageTag(locale), { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone });

    return (
        <div className="status-card">
            <p className="status-label">{t('Server Time', 'Horário do Servidor')}</p>
            <p className="status-value status-number text-gold">
                <time dateTime={now.toISOString()}>{time(timezone)}</time>
            </p>
            <p className="status-note">
                {timezone} · {t('your time', 'seu horário')} {time()}
            </p>
        </div>
    );
}

export default function About({ status, events }: Props) {
    usePoll(30_000, { only: ['status', 'events'] });
    const locale = useLocale();
    const t = useT();
    const fmt = (n: number) => n.toLocaleString(languageTag(locale));

    const { server, rates, races = [], top, castles = [] } = status;
    const raceTotal = races.reduce((sum, race) => sum + race.count, 0);
    const updated = new Date(status.generatedAt).toLocaleTimeString(languageTag(locale), { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const address = server.address ?? t('server-address', 'endereço-do-servidor');
    // The redirect targets the server's DNS name when it has one, so players keep working if its IP changes.
    const connectHost = server.hostname ?? address;

    return (
        <>
            <PageHero
                className="about-hero"
                kicker={t('Server Information', 'Informações do Servidor')}
                title={server.name}
                tagline={t(
                    `A Lineage II ${server.chronicle} server. Live status of the realm, updated every 30 seconds.`,
                    `Um servidor de Lineage II ${server.chronicle}. Status do reino ao vivo, atualizado a cada 30 segundos.`,
                )}
                video={{ src: '/assets/video/about-hero.mp4', poster: '/assets/video/about-hero-poster.jpg' }}
            >
                <p className="about-updated" aria-live="polite">
                    {t('Updated', 'Atualizado às')} {updated}
                    {status.status.database ? '' : t(' · world statistics temporarily unavailable', ' · estatísticas do mundo temporariamente indisponíveis')}
                </p>
            </PageHero>

            <section className="about-section" id="status">
                <div className="container">
                    <div className="status-grid">
                        <StatusCard label={t('Login Server', 'Servidor de Login')} up={status.status.login} note={`${t('Port', 'Porta')} ${server.loginPort}`} />
                        <StatusCard label={t('Game Server', 'Servidor do Jogo')} up={status.status.game} note={`${t('Port', 'Porta')} ${server.gamePort}`} />
                        <div className="status-card">
                            <p className="status-label">Geodata</p>
                            <p className="status-value status-number text-gold">{fmt(server.geodataRegions)}</p>
                            <p className="status-note">{t('Regions loaded', 'Regiões carregadas')}</p>
                        </div>
                        <ServerClock timezone={server.timezone} />
                    </div>
                </div>
            </section>

            <section className="about-section" id="free-server">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t(`What is L2 ${server.name}?`, `O que é o L2 ${server.name}?`)}</p>
                        <h2 className="reveal section-title text-gold">{t('A Free Server', 'Um Servidor Gratuito')}</h2>
                        <Divider />
                        <p className="reveal lead">
                            {t(
                                `L2 ${server.name} is a free, community-run alternative to the official Lineage II servers, on Lineage II ${server.chronicle}.`,
                                `O L2 ${server.name} é uma alternativa gratuita e mantida pela comunidade aos servidores oficiais de Lineage II, no Lineage II ${server.chronicle}.`,
                            )}
                        </p>
                    </div>
                    <div className="features-grid">
                        {FREE_SERVER.map((item, i) => (
                            <div key={item.title.en} className="reveal feature" style={{ transitionDelay: `${i * 120}ms` }}>
                                <span className="feature-num">{String(i + 1).padStart(2, '0')}</span>
                                <h3>{pick(locale, item.title)}</h3>
                                <p>{pick(locale, item.text)}</p>
                            </div>
                        ))}
                    </div>
                    <p className="about-footnote">
                        {t('Powered by', 'Feito com')}{' '}
                        <a className="text-link" href="https://gitlab.com/MobiusDevelopment/L2J_Mobius" target="_blank" rel="noopener noreferrer">
                            L2J Mobius
                        </a>
                        {t(', free software under the', ', software livre sob a licença')}{' '}
                        <a className="text-link" href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener noreferrer">
                            GNU GPL v3
                        </a>
                        {t('. Lineage II is a trademark of NCSOFT Corporation.', '. Lineage II é uma marca registrada da NCSOFT Corporation.')}
                    </p>
                </div>
            </section>

            <HappeningSection feed={events} mode="full" />

            <section className="about-section" id="rates">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t("Read from the server's config", 'Lidas da configuração do servidor')}</p>
                        <h2 className="reveal section-title text-gold">{t('Server Rates', 'Rates do Servidor')}</h2>
                        <Divider />
                    </div>
                    <div className="about-rates">
                        {RATE_CARDS.map(([key, label]) => (
                            <div key={key} className="rate">
                                <div className="rate-value text-gold">x{rates[key]}</div>
                                <div className="rate-label">{pick(locale, label)}</div>
                            </div>
                        ))}
                    </div>
                    <p className="about-footnote">
                        {t(
                            `Party XP bonus, herbs, manor and quest item drops are retail (x1). Max level ${server.maxLevel}.`,
                            `Bônus de XP em grupo, ervas, manor e drops de itens de missão são retail (x1). Nível máximo ${server.maxLevel}.`,
                        )}
                    </p>
                </div>
            </section>

            <section className="about-section" id="world-stats">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('The realm in numbers', 'O reino em números')}</p>
                        <h2 className="reveal section-title text-gold">{t('World Statistics', 'Estatísticas do Mundo')}</h2>
                        <Divider />
                    </div>
                    <div className="totals-grid">
                        {(['accounts', 'characters', 'clans'] as const).map((key) => (
                            <div key={key} className="total">
                                <p className="total-value text-gold">{status.totals ? fmt(status.totals[key]) : '—'}</p>
                                <p className="total-label">{pick(locale, TOTALS[key])}</p>
                            </div>
                        ))}
                    </div>
                    <h3 className="about-subtitle">{t('Races of Aden', 'Raças de Aden')}</h3>
                    <div className="race-bars">
                        {races.length ? (
                            races.map((race) => {
                                const pct = raceTotal ? Math.round((race.count / raceTotal) * 100) : 0;

                                return (
                                    <div key={race.name} className="race-bar">
                                        <span className="race-bar-name">{raceName(locale, race.name)}</span>
                                        <span className="race-bar-track">
                                            <span className="race-bar-fill" style={{ width: `${pct}%` }}></span>
                                        </span>
                                        <span className="race-bar-count">
                                            {fmt(race.count)} · {pct}%
                                        </span>
                                    </div>
                                );
                            })
                        ) : (
                            <p className="about-empty">{t('Waiting for the realm…', 'Aguardando o reino…')}</p>
                        )}
                    </div>
                </div>
            </section>

            <section className="about-section" id="rankings">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('Heroes of the realm', 'Heróis do reino')}</p>
                        <h2 className="reveal section-title text-gold">{t('Top Players', 'Melhores Jogadores')}</h2>
                        <Divider />
                    </div>
                    <div className="rankings-grid">
                        <Ranking title={t('Highest Level', 'Maior Nível')} rows={(top?.level ?? []).map((row) => ({ ...row, value: `Lv ${row.level}` }))} />
                        <Ranking title={t('Most PvP Kills', 'Mais Vitórias PvP')} rows={(top?.pvp ?? []).map((row) => ({ ...row, value: fmt(row.pvp) }))} />
                    </div>
                </div>
            </section>

            <section className="about-section" id="castles">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('Lords of the land', 'Senhores da terra')}</p>
                        <h2 className="reveal section-title text-gold">{t('Castles', 'Castelos')}</h2>
                        <Divider />
                    </div>
                    <div className="castle-grid">
                        {castles.map((castle) => (
                            <div key={castle.name} className={`castle${castle.owner ? ' is-owned' : ''}`}>
                                <p className="castle-name">{castle.name}</p>
                                <p className="castle-owner">{castle.owner ?? t('Unclaimed', 'Sem dono')}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="about-section about-connect" id="connect">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('Join the realm', 'Entre no reino')}</p>
                        <h2 className="reveal section-title text-gold">{t('How to Connect', 'Como se Conectar')}</h2>
                        <Divider />
                    </div>
                    <ol className="connect-steps">
                        <li>
                            <h3>{t('Get the client', 'Consiga o cliente')}</h3>
                            <p>
                                <a className="btn btn-outline btn-small" href={CLIENT_DOWNLOAD_URL} target="_blank" rel="noopener noreferrer">
                                    {t('Download Client', 'Baixar Cliente')}
                                </a>
                            </p>
                        </li>
                        <li>
                            <h3>{t('Point it to the server', 'Aponte para o servidor')}</h3>
                            {server.loginPort !== 2106 ? (
                                <>
                                    <p>
                                        {t(
                                            <>
                                                The Samurai Crow client always logs in on port 2106, but this server's login port is <strong>{server.loginPort}</strong>.
                                                Windows can redirect it for you, in three steps:
                                            </>,
                                            <>
                                                O cliente Samurai Crow sempre entra pela porta 2106, mas a porta de login deste servidor é{' '}
                                                <strong>{server.loginPort}</strong>. O Windows pode fazer o redirecionamento, em três passos:
                                            </>,
                                        )}
                                    </p>
                                    <p>
                                        {t(
                                            <>
                                                <strong>1.</strong> Download both files into the client's <code>system</code> folder:
                                            </>,
                                            <>
                                                <strong>1.</strong> Baixe os dois arquivos para a pasta <code>system</code> do cliente:
                                            </>,
                                        )}
                                    </p>
                                    <p className="connect-downloads">
                                        <a className="btn btn-outline btn-small" href={CLIENT_INI_URL} download>
                                            l2-kage.ini
                                        </a>
                                        <a className="btn btn-outline btn-small" href={CLIENT_START_URL} download>
                                            start-l2-kage.bat
                                        </a>
                                    </p>
                                    <p>
                                        {t(
                                            <>
                                                <strong>2.</strong> Open the Command Prompt <strong>as Administrator</strong> (Start, type <code>cmd</code>, right-click it,
                                                Run as administrator) and run this once:
                                            </>,
                                            <>
                                                <strong>2.</strong> Abra o Prompt de Comando <strong>como Administrador</strong> (Iniciar, digite <code>cmd</code>, clique com
                                                o botão direito, Executar como administrador) e rode uma vez:
                                            </>,
                                        )}
                                    </p>
                                    <pre>
                                        <code>{`netsh interface portproxy add v4tov4 listenaddress=127.0.0.2 listenport=2106 connectaddress=${connectHost} connectport=${server.loginPort}`}</code>
                                    </pre>
                                    <p>
                                        {t(
                                            <>
                                                <strong>3.</strong> Start the game with <code>start-l2-kage.bat</code> (not the launcher). It opens the client with{' '}
                                                <code>l2-kage.ini</code>, which logs in through the redirect.
                                            </>,
                                            <>
                                                <strong>3.</strong> Abra o jogo pelo <code>start-l2-kage.bat</code> (não pelo launcher). Ele abre o cliente com o{' '}
                                                <code>l2-kage.ini</code>, que entra pelo redirecionamento.
                                            </>,
                                        )}
                                    </p>
                                    <p>
                                        {t(
                                            'The redirect stays after restarting Windows. To remove it, run this as Administrator:',
                                            'O redirecionamento continua depois de reiniciar o Windows. Para removê-lo, rode como Administrador:',
                                        )}
                                    </p>
                                    <pre>
                                        <code>netsh interface portproxy delete v4tov4 listenaddress=127.0.0.2 listenport=2106</code>
                                    </pre>
                                    <p>
                                        {t(
                                            <>
                                                To see your redirects: <code>netsh interface portproxy show v4tov4</code>
                                            </>,
                                            <>
                                                Para ver seus redirecionamentos: <code>netsh interface portproxy show v4tov4</code>
                                            </>,
                                        )}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p>
                                        {t(
                                            <>
                                                Set <code>ServerAddr={address}</code> in the patched system's <code>L2.ini</code>, or add this line to your{' '}
                                                <code>hosts</code> file (as Administrator):
                                            </>,
                                            <>
                                                Defina <code>ServerAddr={address}</code> no <code>L2.ini</code> do system com patch, ou adicione esta linha ao seu
                                                arquivo <code>hosts</code> (como Administrador):
                                            </>,
                                        )}
                                    </p>
                                    <pre>
                                        <code>{address} l2authd.lineage2.com</code>
                                    </pre>
                                </>
                            )}
                        </li>
                        <li>
                            <h3>{t('Create your account and log in', 'Crie sua conta e entre')}</h3>
                            <p>
                                {t(
                                    <>
                                        <Link href="/register" className="text-link">Create your account here</Link> with your email, then log in to the game with that
                                        account name and password.
                                    </>,
                                    <>
                                        <Link href="/register" className="text-link">Crie sua conta aqui</Link> com seu e-mail e depois entre no jogo com esse nome de
                                        conta e senha.
                                    </>,
                                )}
                                {server.autoCreateAccounts
                                    ? t(
                                          ' (Logging in with a new name also creates an account, but without an email you could never reset its password.)',
                                          ' (Entrar com um nome novo também cria uma conta, mas sem e-mail você nunca poderia redefinir a senha dela.)',
                                      )
                                    : ''}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>
        </>
    );
}
