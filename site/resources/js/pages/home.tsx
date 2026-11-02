import { Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Divider, Embers, LazyVideo } from '@/components/decor';
import { HappeningSection, LivePill } from '@/components/happening';
import { FeaturedBanner, NewsCard } from '@/components/news';
import RacesSection from '@/components/races-section';
import { CLIENT_DOWNLOAD_URL } from '@/data/links';
import { languageTag, pick, useLocale, useT, type Localized } from '@/i18n';
import type { EventFeed, HomeStoreOffer, NewsBanner, NewsCard as NewsCardData, ServerStatus, SharedProps } from '@/types';

type Props = {
    events?: EventFeed | null;
    rates: ServerStatus['rates'];
    store: HomeStoreOffer;
    news: NewsCardData[];
    banner: NewsBanner | null;
};

/** The Weekly Gift Box's picture until one is uploaded in the admin panel. */
const GIFT_ART = '/assets/kage-pass-chest.jpg?v=2';

/**
 * The Store on the landing page: the weekly gift box, the Kage Pass and the class packages, each with its art, linking to
 * where they are bought.
 */
function StoreSection({ store }: { store: HomeStoreOffer }) {
    const locale = useLocale();
    const t = useT();
    const tag = languageTag(locale);
    const fmt = (n: number) => n.toLocaleString(tag);
    const { gift, pass, packages } = store;
    const passEnds = pass?.endsAt ? new Date(pass.endsAt).toLocaleDateString(tag, { day: 'numeric', month: 'short', year: 'numeric' }) : null;
    const cards = [
        ...(gift
            ? [
                  {
                      id: 'gift-box',
                      href: '/store/weekly-gift-box',
                      art: gift.image ?? GIFT_ART,
                      kicker: t('Every week, free', 'Toda semana, grátis'),
                      title: t('Weekly Gift Box', 'Caixa de Presente Semanal'),
                      text: t(
                          `One box per account every week for a character of level ${gift.minLevel} or higher, with L-Coin and supplies inside.`,
                          `Uma caixa por conta toda semana para um personagem de nível ${gift.minLevel} ou mais, com L-Coin e suprimentos dentro.`,
                      ),
                      price: t('Free', 'Grátis'),
                      cta: t('Open your box', 'Abrir sua caixa'),
                      open: gift.open,
                  },
              ]
            : []),
        ...(pass
            ? [
                  {
                      id: 'kage-pass',
                      href: '/store/kage-pass',
                      art: pass.image ?? '/assets/kage-pass-ticket.jpg',
                      kicker: pass.open && passEnds ? `${t('Season pass', 'Passe da temporada')} · ${t('until', 'até')} ${passEnds}` : t('Season pass', 'Passe da temporada'),
                      title: pick(locale, pass.name),
                      text: t(
                          'A season bundle of supplies and scrolls, once per account. Every new hero starts with enough L-Coin to claim it on day one.',
                          'Um pacote da temporada com suprimentos e pergaminhos, uma vez por conta. Todo herói novo já começa com L-Coin suficiente para resgatá-lo no primeiro dia.',
                      ),
                      price: pass.price ? `${fmt(pass.price.count)} ${pass.price.name}` : t('Free', 'Grátis'),
                      cta: t('Get the pass', 'Garantir o passe'),
                      open: pass.open,
                  },
              ]
            : []),
        ...(packages
            ? [
                  {
                      id: 'packages',
                      href: '/store?category=packages#store',
                      art: packages.image ?? '/assets/packages-chest.webp',
                      kicker: t('Full gear for your class', 'Equipamento completo para sua classe'),
                      title: t('Class Packages', 'Pacotes de Classe'),
                      text: t(
                          `Weapon, armor and jewelry for ${packages.groups} archetypes in three tiers, from a Starter kit to Elite boss weapons.`,
                          `Arma, armadura e joias para ${packages.groups} arquétipos em três níveis, do kit Iniciante às armas de chefe Elite.`,
                      ),
                      price: `${fmt(packages.min)} – ${fmt(packages.max)} ${packages.currency}`,
                      cta: t('Choose a package', 'Escolher um pacote'),
                      open: packages.open,
                  },
              ]
            : []),
    ];

    return (
        <section id="store" className="store-section">
            <div className="container">
                <div className="section-head">
                    <p className="reveal kicker">{t('Gifts, passes & packages', 'Presentes, passes e pacotes')}</p>
                    <h2 className="reveal section-title text-gold">{t('The Store', 'A Loja')}</h2>
                    <Divider />
                    <p className="reveal lead">
                        {t(
                            'Each week, the goddess Einhasad leaves a gift for every hero who walks Aden. Seal a season’s pact with the Kage Pass, or arm yourself for the wars to come with gear forged for your calling. Every reward rides to you by courier, straight to your mailbox in game.',
                            'Toda semana, a deusa Einhasad deixa um presente para cada herói que percorre Aden. Firme o pacto da temporada com o Kage Pass, ou arme-se para as guerras que virão com equipamento forjado para a sua vocação. Cada recompensa segue até você por mensageiro, direto à sua caixa de correio no jogo.',
                        )}
                    </p>
                </div>
                <div className="store-cards">
                    {cards.map((card, i) => (
                        <article key={card.id} className="reveal store-card" style={{ transitionDelay: `${i * 120}ms` }}>
                            <div className="store-card-art">
                                <img src={card.art} alt="" loading="lazy" />
                            </div>
                            <div className="store-card-body">
                                <p className="store-card-kicker">{card.kicker}</p>
                                <h3 className="store-card-title text-gold">{card.title}</h3>
                                <p className="store-card-text">{card.text}</p>
                                <p className="store-card-price">{card.price}</p>
                                {card.open ? (
                                    <a href={card.href} className="btn btn-primary btn-small">
                                        {card.cta}
                                    </a>
                                ) : (
                                    <p className="gift-next">{t('Closed for now.', 'Fechado por enquanto.')}</p>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
                <div className="actions store-actions">
                    <Link href="/store" className="btn btn-outline">
                        {t('Visit the Store', 'Visitar a Loja')}
                    </Link>
                </div>
            </div>
        </section>
    );
}

/** The latest posts, written in the admin panel (News). Hidden until there is one. */
function NewsSection({ posts }: { posts: NewsCardData[] }) {
    const t = useT();

    if (!posts.length) {
        return null;
    }

    return (
        <section id="news" className="news-section">
            <div className="container">
                <div className="section-head">
                    <p className="reveal kicker">{t('The latest from the realm', 'O mais recente do reino')}</p>
                    <h2 className="reveal section-title text-gold">{t('News from Aden', 'Notícias de Aden')}</h2>
                    <Divider />
                </div>
                <div className="news-grid">
                    {posts.map((post, i) => (
                        <NewsCard key={post.slug} post={post} style={{ transitionDelay: `${i * 120}ms` }} />
                    ))}
                </div>
                <div className="reveal actions">
                    <Link href="/news" className="btn btn-outline">{t('All News', 'Todas as Notícias')}</Link>
                </div>
            </div>
        </section>
    );
}

const GIFT_BANNER_KEY = 'gift-banner-closed';

/** A corner banner for the weekly gift box. Closing it is remembered in this browser. */
function GiftBanner({ image }: { image: string | null }) {
    const t = useT();
    const [open, setOpen] = useState(false);

    useEffect(() => {
        try {
            setOpen(localStorage.getItem(GIFT_BANNER_KEY) === null);
        } catch {
            setOpen(true);
        }
    }, []);

    const close = () => {
        setOpen(false);
        try {
            localStorage.setItem(GIFT_BANNER_KEY, '1');
        } catch {
            // The banner just comes back on the next visit.
        }
    };

    if (!open) {
        return null;
    }

    return (
        <aside className="gift-banner">
            <a href="/store/weekly-gift-box" className="gift-banner-link">
                <img src={image ?? GIFT_ART} width={56} height={56} alt="" />
                <span>
                    <span className="gift-banner-kicker">{t('Every week, free', 'Toda semana, grátis')}</span>
                    <span className="gift-banner-title text-gold">{t('Weekly Gift Box', 'Caixa de Presente Semanal')}</span>
                    <span className="gift-banner-cta">{t('Open your box', 'Abrir sua caixa')}</span>
                </span>
            </a>
            <button type="button" className="gift-banner-close" onClick={close} aria-label={t('Close', 'Fechar')}>
                ×
            </button>
        </aside>
    );
}

const LEGENDS: { href: string; chapter: Localized; title: Localized; text: Localized }[] = [
    { href: '/lore#giants', chapter: { en: 'Chapter III', pt: 'Capítulo III' }, title: { en: 'The Fall of the Giants', pt: 'A Queda dos Gigantes' },
        text: { en: 'They dared to create life. Einhasad answered with the Hammer of Despair, and Grandferion fell in a single day.',
            pt: 'Ousaram criar vida. Einhasad respondeu com o Martelo do Desespero, e Grandferion caiu em um único dia.' } },
    { href: '/lore#shilen', chapter: { en: 'Chapter IV', pt: 'Capítulo IV' }, title: { en: "Shilen's Betrayal", pt: 'A Traição de Shilen' },
        text: { en: 'The goddess of water fell to the abyss. Her children became the Dark Elves, and her dragons sleep, waiting.',
            pt: 'A deusa da água caiu no abismo. Seus filhos se tornaram os Elfos Negros, e seus dragões dormem, à espera.' } },
    { href: '/lore#samurai', chapter: { en: 'Chapter XIII', pt: 'Capítulo XIII' }, title: { en: 'The Samurai Crow', pt: 'O Samurai Crow' },
        text: { en: 'The Kamael were made as weapons. Now one of them chooses his own blade, and takes up the katana.',
            pt: 'Os Kamael foram criados como armas. Agora um deles escolhe a própria lâmina e empunha a katana.' } },
];

const FEATURES: { title: Localized; text: Localized }[] = [
    { title: { en: 'Castle Sieges', pt: 'Cercos aos Castelos' },
        text: { en: 'Two thrones, Giran and Goddard. Forge alliances, raise banners and claim the castle.',
            pt: 'Dois tronos, Giran e Goddard. Forme alianças, erga estandartes e conquiste o castelo.' } },
    { title: { en: 'Orc Fortress', pt: 'Fortaleza Orc' },
        text: { en: 'Clans clash for the fortress of the Orcs and the power it grants its holders.',
            pt: 'Clãs se enfrentam pela fortaleza dos Orcs e pelo poder que ela concede a quem a domina.' } },
    { title: { en: 'Epic Raids', pt: 'Raids Épicas' },
        text: { en: 'Antharas, Baium, Zaken and Queen Ant await the bravest clans.', pt: 'Antharas, Baium, Zaken e a Queen Ant aguardam os clãs mais corajosos.' } },
    { title: { en: 'Grand Olympiad', pt: 'Grande Olimpíada' },
        text: { en: 'Prove yourself in the arena and earn the glory of Hero status.', pt: 'Prove seu valor na arena e conquiste a glória de se tornar Herói.' } },
];

export default function Home({ events, rates, store, news, banner }: Props) {
    usePoll(30_000, { only: ['events'] });
    const { serverName } = usePage<SharedProps>().props;
    const locale = useLocale();
    const t = useT();

    const landingRates = [
        { value: rates.xp, label: t('Experience', 'Experiência') },
        { value: rates.adena, label: 'Adena' },
        { value: rates.drop, label: 'Drop' },
        { value: rates.raid, label: t('Raid Boss', 'Raid Boss') },
    ];

    return (
        <>
            {store.gift ? <GiftBanner image={store.gift.image} /> : null}
            <section className="hero">
                <div className="hero-media" data-parallax="hero">
                    {/* The Samurai Crow update's intro: the Kamael Samurai, the server's current chapter. */}
                    <video src="/assets/video/samurai-crow-hero.webm" poster="/assets/hero.jpg" autoPlay muted loop playsInline></video>
                </div>
                <div className="hero-shade"></div>
                <div className="hero-fade bg-fade"></div>
                <Embers />
                <div className="hero-content" data-parallax="hero-content">
                    <LivePill feed={events} />
                    <p className="hero-kicker">{t('A New Blade Is Drawn', 'Uma Nova Lâmina Foi Desembainhada')}</p>
                    <h1 className="hero-title text-gold">L2 {serverName.toUpperCase()}</h1>
                    <p className="hero-subtitle">ESSENCE · SAMURAI CROW</p>
                    <Divider />
                    <p className="hero-tagline">
                        {t(
                            'The dragons are awake. The Kamael take up the katana. Aden calls for heroes once more.',
                            'Os dragões despertaram. Os Kamael empunham a katana. Aden chama seus heróis mais uma vez.',
                        )}
                    </p>
                    <div className="actions">
                        <a href="#join" className="btn btn-primary">{t('Start Your Journey', 'Comece Sua Jornada')}</a>
                        <a href="#world" className="btn btn-outline">{t('Discover Aden', 'Descubra Aden')}</a>
                    </div>
                </div>
                <a href="#world" className="scroll-hint">{t('Scroll', 'Role')}</a>
            </section>

            <FeaturedBanner banner={banner} />

            <HappeningSection feed={events} mode="compact" />

            <NewsSection posts={news} />

            <StoreSection store={store} />

            <section id="world" className="world">
                <div className="world-bg" data-parallax="world"></div>
                <div className="world-shade"></div>
                <div className="world-content">
                    <p className="reveal kicker">{t('The World of Aden', 'O Mundo de Aden')}</p>
                    <h2 className="reveal section-title text-gold">{t('A Kingdom Forged in War', 'Um Reino Forjado na Guerra')}</h2>
                    <Divider />
                    <p className="reveal lead">
                        {t(
                            "From the depths of Dragon Valley to the frozen halls of Frost Lord's Castle, the continent of Aden is torn between two thrones and countless clans. In the age of the Samurai Crow, Antharas wakes beneath the earth, the Source of Evil opens its sealed rooms, and only the strongest will write history.",
                            'Das profundezas do Vale do Dragão aos salões congelados do Castelo do Frost Lord, o continente de Aden está dividido entre dois tronos e incontáveis clãs. Na era do Samurai Crow, Antharas desperta sob a terra, a Fonte do Mal abre suas salas seladas, e só os mais fortes vão escrever a história.',
                        )}
                    </p>
                    <div className="reveal actions">
                        <Link href="/map" className="btn btn-outline">{t('Explore the Live Map', 'Explore o Mapa ao Vivo')}</Link>
                    </div>
                </div>
            </section>

            <RacesSection />

            <section id="legends" className="legends">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('History & Lore', 'História e Lendas')}</p>
                        <h2 className="reveal section-title text-gold">{t('Legends of Aden', 'Lendas de Aden')}</h2>
                        <Divider />
                        <p className="reveal lead legends-lead">
                            {t(
                                'From the shattering of the world to the age of the Samurai Crow: the gods, the Giants and the empires whose ruins you will fight over.',
                                'Da ruptura do mundo à era do Samurai Crow: os deuses, os Gigantes e os impérios por cujas ruínas você vai lutar.',
                            )}
                        </p>
                    </div>
                    <div className="legends-grid">
                        {LEGENDS.map((legend, i) => (
                            <a key={legend.href} href={legend.href} className="reveal legend" style={{ transitionDelay: `${i * 120}ms` }}>
                                <span className="legend-num">{pick(locale, legend.chapter)}</span>
                                <h3>{pick(locale, legend.title)}</h3>
                                <p>{pick(locale, legend.text)}</p>
                                <span className="legend-more">{t('Read the chapter', 'Ler o capítulo')}</span>
                            </a>
                        ))}
                    </div>
                    <div className="reveal actions">
                        <a href="/lore" className="btn btn-outline">{t('Read the Full History', 'Ler a História Completa')}</a>
                    </div>
                </div>
            </section>

            <section id="features" className="features">
                <div className="container">
                    <div className="section-head">
                        <p className="reveal kicker">{t('Server Features', 'Recursos do Servidor')}</p>
                        <h2 className="reveal section-title text-gold">{t('Glory Awaits', 'A Glória Aguarda')}</h2>
                        <Divider />
                    </div>
                    <div className="features-grid">
                        {FEATURES.map((feature, i) => (
                            <div key={feature.title.en} className="reveal feature" style={{ transitionDelay: `${i * 120}ms` }}>
                                <span className="feature-num">{String(i + 1).padStart(2, '0')}</span>
                                <h3>{pick(locale, feature.title)}</h3>
                                <p>{pick(locale, feature.text)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="rates" className="rates">
                <div className="rates-media">
                    <video src="/assets/video/home-hero.mp4" autoPlay muted loop playsInline></video>
                </div>
                <div className="rates-shade"></div>
                <div className="rates-content">
                    <h2 className="reveal section-title text-gold">{t('Server Rates', 'Rates do Servidor')}</h2>
                    <Divider />
                    {/* Read from the server's Rates.ini (overrides over defaults), so they always match the server. */}
                    <div className="rates-grid">
                        {landingRates.map((rate) => (
                            <div key={rate.label} className="reveal rate">
                                <div className="rate-value text-gold">x{rate.value}</div>
                                <div className="rate-label">{rate.label}</div>
                            </div>
                        ))}
                    </div>
                    <div className="reveal actions rates-actions">
                        <a href="/about" className="btn btn-outline">{t('Live Server Status', 'Status do Servidor ao Vivo')}</a>
                    </div>
                </div>
            </section>

            <section id="join" className="join">
                <div className="join-media">
                    <LazyVideo src="/assets/video/home-join.webm" />
                </div>
                <div className="join-shade"></div>
                <div className="join-content">
                    <h2 className="reveal join-title text-gold">{t('Your Legend Begins', 'Sua Lenda Começa')}</h2>
                    <Divider />
                    <p className="reveal lead">
                        {t('Create your account, download the client and step into Aden today.', 'Crie sua conta, baixe o cliente e entre em Aden hoje mesmo.')}
                    </p>
                    <div className="reveal actions">
                        <a href="/register" className="btn btn-primary">{t('Create Account', 'Criar Conta')}</a>
                        <a href={CLIENT_DOWNLOAD_URL} className="btn btn-outline" target="_blank" rel="noopener noreferrer">
                            {t('Download Client', 'Baixar Cliente')}
                        </a>
                    </div>
                </div>
            </section>
        </>
    );
}
