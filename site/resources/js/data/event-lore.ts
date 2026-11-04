import { EVENT_LORE_PT } from '@/data/event-lore-pt';
import type { Locale } from '@/i18n';
import type { GameEvent } from '@/types';

/**
 * "Learn more" texts for the Happening Now cards: the story behind each event (consistent with /lore) and how it
 * works on this server. Rules come from the server's own config and scripts (Siege.ini, Olympiad.ini, the boss AI
 * scripts of L2J Mobius Essence); keep them in sync if those change. The Portuguese texts are in event-lore-pt.ts.
 */
export type EventLore = {
    lore: string[];
    howItWorks: string[];
    chapter?: { href: string; label: string };
};

/** Every "Learn more" text in one language. */
export type EventLoreSet = {
    olympiad: EventLore;
    siege: EventLore;
    raid: EventLore;
    seasonal: Record<string, EventLore>;
    epic: Record<string, EventLore>;
};

const OLYMPIAD: EventLore = {
    lore: [
        'When the bravest adventurers learned the secrets of the Giants and rose as Noblesse, Aden needed a way to crown the best among them. The Grand Olympiad was born: one arena, one opponent, and only skill decides.',
        'Each season ends with a new Hero for every class, whose name is remembered across the land.',
    ],
    howItWorks: [
        'Only Noblesse can compete. Register with an Olympiad Manager while the arena is open: on Fridays and Saturdays from 20:00 for 4 hours (server time).',
        'Fight in class-based or all-class matches to win points; at least 10 matches in a season are needed to be considered for Hero.',
        'At the end of each season the top Noblesse of every class becomes Hero, with Hero weapons, Hero skills and a glowing aura.',
    ],
    chapter: { href: '/lore#chaos', label: 'Chapter VII: The Chaotic Era' },
};

const SIEGE: EventLore = {
    lore: [
        'Since the Battle for Giran Castle, the old rules of siege are law: whoever takes a castle becomes its rightful lord, whatever their birth. The kings of Aden can only confirm the outcome.',
        'In this age only two thrones are fought over: Giran and Goddard. Each brings a treasury, a castle owner skill and the respect, or the envy, of every clan in Aden.',
    ],
    howItWorks: [
        'Clans of level 3 or higher register as attackers or defenders before the siege. Sieges come every two weeks.',
        'A siege lasts 60 minutes. Attackers must break through the walls and gates, and their clan leader must engrave the clan seal on the castle’s Holy Artifact.',
        'The clan that holds the castle when time runs out rules it until the next siege, and the Victory Herald comes to reward its leader.',
    ],
    chapter: { href: '/lore#frost', label: 'Chapter X: Frost and Wind' },
};

const RAID: EventLore = {
    lore: [
        'Fallen champions, ancient beasts and the generals of forgotten wars roam the wild lands of Aden. They are stronger than any single adventurer, and they guard treasures to match.',
    ],
    howItWorks: [
        'Raid bosses stand at fixed places around the world, from level 20 to the end game. Gather a party, find one of your level and bring it down.',
        'They drop rare materials, recipes and equipment, and give Raid Points.',
        'A slain raid boss returns a day or two later. The About page shows when the most recent ones are due.',
    ],
};

/** Seasonal events, by their folder in data/scripts/events (the card id is "event-<folder>"). */
const SEASONAL: Record<string, EventLore> = {
    SquashEvent: {
        lore: ['Gardener Fordson of Floran has sown his magical seeds for one more autumn, and on Halloween they grow strange. His cat, Puss, is looking for heroes to tend the pumpkins, and whatever grows inside them is yours to keep.'],
        howItWorks: [
            'Monsters across Aden can drop Nectar. Trade it to Puss the Cat in town: 10 for a seed, 20 for a large seed, or 100 or more for a chrono instrument.',
            'Double-click a seed to plant it, then use Nectar on the young pumpkin until it changes. It may turn ripe or withered, and ripe ones hold better prizes. Pumpkins vanish after a few minutes, so be quick.',
            'Break the pumpkin to claim its prizes. Large pumpkins can only be broken with a chrono instrument, and they can hold Halloween Kits.',
        ],
    },
    HolidayOutOfSchedule: {
        lore: ['Santa Claus has come to Aden for the winter, but on the way a spell turned his reindeer, Rudolph, into a man. With no one to pull the sleigh, Santa walks the hunting grounds on foot, and Rudolph sends heroes to find him.'],
        howItWorks: [
            'Talk to Humanized Rudolph in any town. Every day at 15:00 and 22:00 UTC (server time), Santa appears for one hour in a different hunting ground, and Rudolph can teleport you to him.',
            'Ask Santa for your gift: an L-Coin Pouch, a Giant\'s Shining Relic Pouch, a New Year Gift Box, Dye Powder and 10 each of Scroll: Boost Attack and Scroll: Boost Defense. One gift per account each day; the day changes at 00:00 UTC.',
            'Stand near a Christmas tree, in Aden, in Giran or beside Santa, for the Holiday Blessing: +100 HP and MP and +10% XP and SP for 30 minutes.',
        ],
    },
    LeonasDungeonEvent: {
        lore: [
            'Leona Blackbird is a warrior of legend. Her dungeon is a space that unfolds with her power and memory: a reenactment of the moment she awakened, where every battle trains adventurers to become heroes.',
            'Those who cannot wield her spear (wizards, healers and Sylphs) may borrow her weapon or even take her form while they fight inside.',
        ],
        howItWorks: [
            'Speak with Leona Blackbird in Giran or Aden. You must enter as a command channel of 2 to 40 players, each of level 76 or higher.',
            'Inside, choose the difficulty (beginner, intermediate or advanced) and hold out for 30 minutes. Leona can lend you her weapon, transform you, or grant her blessing for 50,000 Adena.',
            'Your score is ranked: ask Leona for the Dungeon Ranking to see the top 20. You can enter once a day; entry resets every morning at 06:30 (server time).',
        ],
    },
    LegendOfTheWaterTemple: {
        lore: [
            "The Mother Tree of the Elves is fading. Its researchers have learned that the Wondrous Water of Life could restore it, but the Water Temple has been invaded by monsters that pretend to be Water Spirits and claim to follow Eva.",
            'At the top of the temple wait Aber, Eva\'s Adept, and Elle, who calls herself the Goddess of Water.',
        ],
        howItWorks: [
            'Speak with Mother Tree Researcher Adella in Giran or Aden, with a party of 2 to 5 players of level 10 or higher.',
            "Inspector Aos sends you to the battlefield. Kill enough monsters on each floor to climb higher, and watch out: the temple can flood. You have 30 minutes.",
            'Stay near the boss when it falls to get your reward. You can enter once a day; entry resets every morning at 06:30 (server time).',
        ],
        chapter: { href: '/lore#gods', label: 'Chapter II: Children of the Gods' },
    },
    LetterCollector: {
        lore: ['Letters have been scattered across the lands of Aden. Alone they mean nothing; put together, they spell words that unseal treasures.'],
        howItWorks: [
            'Monsters of level 40 and above can drop H.O.T, S.U.M.M.E.R and LINEAGE2 word packs, each full of letters.',
            'Open the Letter Collector window and spell HOT, SUMMER or LINEAGE II with your letters to exchange them for that word\'s reward pack.',
        ],
    },
};

/** Epic bosses, by NPC id (same ids as grandboss_data). */
const EPIC: Record<string, EventLore> = {
    '29068': {
        lore: [
            'Antharas, the Earth Dragon, is a child of Shilen and Gran Kain. When Shilen was defeated he went to ground beneath Dragon Valley near Giran, where he sleeps and waits for her return.',
            'Since the age of Dwelling of Spirits his lair is open to all of Aden, and every time the ground of the valley trembles, the people of Giran look to the mountains.',
        ],
        howItWorks: [
            'Gather an army in Dragon Valley and enter his lair. He is one of the strongest beings in Aden: only well-prepared alliances survive.',
            'If he falls, he returns days later. The card shows when.',
        ],
        chapter: { href: '/lore#shilen', label: "Chapter IV: Shilen's Betrayal" },
    },
    '29020': {
        lore: [
            'Emperor Baium of Elmoreden built the Tower of Insolence to reach the gods themselves. The gods answered by sealing him at its summit, frozen in his own monument.',
            'His empire broke in two when he fell: the Kingdom of Aden and the Kingdom of Elmore.',
        ],
        howItWorks: [
            'Climb the Tower of Insolence to the top floor, where Baium sits frozen on his throne. Waking him starts the fight.',
            'He is joined by his Archangels. Bring a full raid group.',
            'Once defeated, he returns days later. The card shows when.',
        ],
        chapter: { href: '/lore#baium', label: 'Chapter VI: The Tower of Insolence' },
    },
    '29022': {
        lore: ['Zaken, the cursed pirate captain, sails the seas around his island with a crew that death could not keep. Whoever boards his ship fights the dead as well as the living.'],
        howItWorks: ['Find Zaken where his ship lies and fight through his crew. A target for strong parties and clans; if slain, he returns later. The card shows when.'],
    },
    '29001': {
        lore: ['Beneath the fields of Aden lies a vast Ant Nest. At its heart Queen Ant breeds an endless brood, guarded by her nurses and royal guards.'],
        howItWorks: [
            "Fight through the Ant Nest to her chamber. Kill her nurses first, or they'll heal her.",
            'A classic target for mid-level clans. If she is slain, she returns a day or two later.',
        ],
    },
    '29006': {
        lore: ['Cruma Tower, a relic of an older age, still hums with ancient machinery. At its core waits Core, guarded by the constructs that serve it.'],
        howItWorks: ["Descend through Cruma Tower to its core chamber. A mid-level clan target; once destroyed, it returns a day or two later."],
    },
    '29014': {
        lore: ['Orfen, the Spore Mistress, haunts the Sea of Spores near Oren, a creature born of the poison that drifts over that land.'],
        howItWorks: ['Orfen wanders the Sea of Spores with her escorts. A mid-level clan target; if slain, she returns a day or two later.'],
    },
};

const EVENT_LORE_EN: EventLoreSet = { olympiad: OLYMPIAD, siege: SIEGE, raid: RAID, seasonal: SEASONAL, epic: EPIC };

export function eventLore(event: GameEvent, locale: Locale = 'en'): EventLore | null {
    const lore = locale === 'pt_BR' ? EVENT_LORE_PT : EVENT_LORE_EN;

    switch (event.category) {
        case 'epic':
            return lore.epic[event.id.replace('epic-', '')] ?? null;
        case 'olympiad':
            return lore.olympiad;
        case 'siege':
            return lore.siege;
        case 'raid':
            return lore.raid;
        case 'event':
            return lore.seasonal[event.id.replace('event-', '')] ?? null;
        default:
            return null;
    }
}
