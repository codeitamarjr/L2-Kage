import type { Localized } from '@/i18n';

export type SharedProps = {
    serverName: string;
    /** The visitor's language (see resources/js/i18n.ts). */
    locale: 'en' | 'pt_BR';
    /** premium: when the account's premium ends and what it gives; null without premium. gifts: what an admin sent that waits for a character. */
    player: { login: string; gm: boolean; premium: { until: string; bonuses: string } | null; gifts: number } | null;
    /** One-off message after a redirect (see HandleInertiaRequests). */
    flash: { status: string | null; sentTo: string | null; login: string | null };
    /** Cloudflare Turnstile site key, or null when the bot check is off. */
    turnstileSiteKey: string | null;
    /** False until an email service is configured: registration and password resets are closed. */
    emailReady: boolean;
};

export type EventState = 'live' | 'soon' | 'scheduled' | 'ready' | 'slain';

/** A spot on the world map, in percent of the map image (see app/Services/L2j/WorldMap.php). */
export type MapPoint = { x: number; y: number; name: string };

export type GameEvent = {
    id: string;
    category: 'epic' | 'raid' | 'siege' | 'olympiad' | 'event';
    title: string;
    level?: number | null;
    place?: string;
    state: EventState;
    headline: string;
    detail?: string;
    startsAt?: string | null;
    endsAt?: string | null;
    returnsAt?: string | null;
    at?: MapPoint[];
};

export type RaidPin = { id: number; name: string; level: number | null; x: number; y: number; alive: boolean; returnsAt: string | null };

/** Every raid boss on the map, plus how many spawn in lands the map doesn't show. */
export type WorldMapData = { raids: RaidPin[]; beyond: number };

export type NewsCard = { slug: string; category: string; title: string; summary: string; image: string | null; publishedAt: string | null };

export type NewsBanner = NewsCard & { until: string | null; theme: string };

export type EventFeed = {
    generatedAt: string;
    live: number;
    events: GameEvent[];
};

export type ServerStatus = {
    generatedAt: string;
    server: {
        name: string;
        chronicle: string;
        maxLevel: number;
        address: string | null;
        hostname: string | null;
        loginPort: number;
        gamePort: number;
        geodataRegions: number;
        autoCreateAccounts: boolean;
    };
    status: { login: boolean; game: boolean; database: boolean };
    rates: Record<'xp' | 'sp' | 'adena' | 'drop' | 'spoil' | 'raid' | 'questXp' | 'questAdena', number>;
    players?: { online: number };
    totals?: { accounts: number; characters: number; clans: number };
    races?: { name: string; count: number }[];
    top?: {
        level: { name: string; level: number; race: string }[];
        pvp: { name: string; pvp: number; level: number; race: string }[];
    };
    castles?: { name: string; owner: string | null }[];
};

export type CharacterSummary = {
    id: number;
    name: string;
    title: string | null;
    level: number;
    className: string;
    baseClassName: string | null;
    race: string;
    sex: string;
    online: boolean;
    clan: string | null;
    noblesse: boolean;
    gm: boolean;
    lastSeen: string | null;
    deleting: boolean;
};

export type CharacterItem = {
    id: number;
    itemId: number;
    name: string;
    type: 'Weapon' | 'Armor' | 'EtcItem';
    grade: string | null;
    kind: string;
    category: 'weapon' | 'armor' | 'jewelry' | 'accessory' | 'etc';
    icon: string | null;
    count: number;
    enchant: number;
    slot?: string;
};

export type CharacterDetail = CharacterSummary & {
    clanLevel: number | null;
    status: {
        hp: { current: number; max: number };
        mp: { current: number; max: number };
        cp: { current: number; max: number };
        exp: number;
        expPercent: number;
        sp: number;
        karma: number;
        fame: number;
        pvp: number;
        pk: number;
        vitality: number;
        onlineSeconds: number;
        created: string | null;
    };
    adena: number;
    items: { equipped: CharacterItem[]; inventory: CharacterItem[]; warehouse: CharacterItem[] };
};

/** The weekly gift box in the player area (app/Services/GiftBox.php). */
/** The home page's store section (app/Services/Store.php homeOffer()); a product that's gone leaves its card out. */
export type HomeStoreOffer = {
    gift: { open: boolean; minLevel: number } | null;
    pass: { open: boolean; name: Localized; endsAt: string | null; price: StorePrice | null } | null;
    packages: { open: boolean; groups: number; min: number; max: number; currency: string } | null;
};

/** A product's price: any item the game knows (L-Coin, Adena...). */
export type StorePrice = { item: number; count: number; name: string };

export type StoreCategory = { key: string; name: Localized };

/** A product on the Store's grid (app/Services/Store.php cards()). */
export type StoreCard = {
    slug: string;
    type: 'mail' | 'weekly_gift' | 'hunt_pass';
    category: string;
    /** Shown on the Store's front page. */
    featured: boolean;
    name: Localized;
    /** null: free. */
    price: StorePrice | null;
    open: boolean;
    /** The cover image, or null for the drawn picture (art, emblem, tier). */
    image: string | null;
    art: 'gift' | 'pass' | 'hunt' | 'package' | 'supplies';
    emblem: string | null;
    tier: string | null;
    /** A class group: the Store can filter by it. */
    group: string | null;
    groupName: Localized | null;
};

export type StoreContentItem = { id?: number | null; name: string; count?: number; enchant?: number; chance?: number };

/** A product's own page (app/Services/Store.php present()). */
export type StoreProduct = StoreCard & {
    /** The uploaded images, the cover first. */
    images: string[];
    endsAt: string | null;
    /** Paragraphs separated by a blank line. */
    description: Localized;
    contents: { title: Localized; items: StoreContentItem[]; note?: Localized }[];
    steps: Localized[];
    /** Limit per account, e.g. "1 per week"; null: as many as the player likes. */
    limit: Localized | null;
    limitPeriod: 'week' | 'month' | 'season' | 'once' | null;
    maxQuantity: number;
    minLevel: number | null;
    hunt: { firstSteps: { free: { name: string; count: number }; premium: { name: string; count: number } }[]; steps: number } | null;
};

/** A signed-in player's side of a product page. balance: what the character holds of the price, as last saved; null when free. */
export type StoreBuyer = {
    characters: { id: number; name: string; level: number; className: string; archetype: string | null; balance: number | null }[];
    /** Already held and can't be bought again for now. */
    owned: { characterName: string | null; status: Order['status']; nextAt: string | null } | null;
};

/** One entry of the Orders tab (app/Services/Orders.php history()). */
export type Order = {
    id: number;
    /** The category when it was ordered. */
    type: string;
    typeName: Localized;
    name: Localized;
    /** null: sent by an admin and waiting for the player to choose. */
    characterName: string | null;
    price: { count: number; currency: string } | null;
    status: 'waiting' | 'pending' | 'processing' | 'done' | 'failed';
    createdAt: string;
    fromStaff: boolean;
    note: string | null;
    /** Waiting: one bonus is rolled when a character is chosen. */
    randomBonus: boolean;
    items: { name: string; count: number; enchant: number; bonus?: boolean }[];
};

/** Who can receive what an admin sent. */
export type OrderRecipient = { id: number; name: string; level: number };

/** Item database (app/Services/L2j/ItemDatabase.php). */
export type ItemSummary = {
    id: number;
    name: string;
    type: 'Weapon' | 'Armor' | 'EtcItem';
    kind: string;
    category: 'weapon' | 'armor' | 'jewelry' | 'accessory' | 'etc';
    grade: string | null;
    /** A real icon from public/assets/icons, or null for the drawn placeholder. */
    icon: string | null;
};

export type ItemRef = { item: ItemSummary | { id: number; name: string }; count: number };

export type ItemSource = {
    npc: { id: number; name: string; title: string | null; level: number; type: string };
    chance: number;
    min: number;
    max: number;
};

export type ItemRecipe = {
    id: number;
    recipe: ItemSummary | { id: number; name: string };
    level: number;
    dwarven: boolean;
    successRate: number;
    mpCost: number;
    product: ItemRef;
    rare: (ItemRef & { chance: number }) | null;
    ingredients: ItemRef[];
};

export type ItemDetail = ItemSummary & {
    weight: number;
    price: number;
    crystals: number | null;
    soulshots: number | null;
    spiritshots: number | null;
    mpConsume: number | null;
    flags: { stackable: boolean; tradable: boolean; droppable: boolean; sellable: boolean; quest: boolean; magic: boolean; herb: boolean; limited: number | null };
    stats: Record<string, { op: 'set' | 'add' | 'mul'; val: number }>;
    drops: { rows: ItemSource[]; total: number };
    spoils: { rows: ItemSource[]; total: number };
    merchants: { id: number; name: string; title: string | null }[];
    craftedBy: ItemRecipe[];
    usedIn: ItemRecipe[];
    teaches: ItemRecipe[];
};
