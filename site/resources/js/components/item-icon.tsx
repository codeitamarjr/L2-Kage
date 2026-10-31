import type { ReactNode } from 'react';
import type { ItemSummary } from '@/types';

/**
 * An item's icon. Real icons (extracted from the game client into public/assets/icons) are used when present;
 * otherwise a drawn placeholder: a glyph for the kind of item in a frame coloured by grade.
 */
export const GLYPHS: Record<string, ReactNode> = {
    sword: <path d="M19 5l-9 9m9-9h-3l-9 9 2 2 9-9V5zM6 15l3 3m-5 2l2-2" />,
    dagger: <path d="M17 7l-6 6m6-6h-2l-6 6 2 2 6-6V7zM8 14l2 2m-5 3l2-2" />,
    dual: <path d="M18 4l-8 8m8-8h-2.5L8 11.5M6 4l8 8M6 4h2.5L16 11.5M8 14l-3 3m11-3l3 3" />,
    blunt: <path d="M6 18l7-7m1-6l5 5-3 3-5-5zM4 20l2-2" />,
    bow: <path d="M6 3c8 3 11 9 9 18M6 3l9 18M3 12h13l-3-3m3 3l-3 3" />,
    pole: <path d="M5 19L17 7m0 0l3-4-4 3m1 1l-1 3m1-3l3-1" />,
    fist: <path d="M7 12V8a1.5 1.5 0 0 1 3 0v3m0-2a1.5 1.5 0 0 1 3 0v2m0-1a1.5 1.5 0 0 1 3 0v4c0 4-2 6-5 6H10c-2 0-3-1.5-3-4z" />,
    chest: <path d="M8 4l-4 3 2 4v9h12v-9l2-4-4-3-2 2h-4zM10 6l2 3 2-3" />,
    legs: <path d="M7 4h10l1 16h-4l-2-10-2 10H6z" />,
    head: <path d="M5 15c0-6 3-10 7-10s7 4 7 10v4H5zm3 0h8m-4-10v6" />,
    gloves: <path d="M7 20v-9l-2-3 2-1 2 2V5h2v5V4h2v6V5h2v6l2-2 1 2-3 5v4z" />,
    feet: <path d="M8 4h5v9l6 3v4H6l2-4z" />,
    shield: <path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6z" />,
    sigil: <path d="M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 4l3 4-3 4-3-4z" />,
    neck: <path d="M5 4c0 7 3 10 7 10s7-3 7-10M12 14v2m-2 1.5L12 21l2-3.5z" />,
    ear: <path d="M12 3v5m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 4l-3 4 3 5 3-5z" />,
    ring: <path d="M12 9a6 6 0 1 0 0 12 6 6 0 0 0 0-12zm-2-6h4l2 3-4 3-4-3z" />,
    accessory: <path d="M4 16l2-9 4 4 2-6 2 6 4-4 2 9z" />,
    potion: <path d="M10 3h4m-3 0v5L6 17a3 3 0 0 0 3 4h6a3 3 0 0 0 3-4l-5-9V3M8 14h8" />,
    scroll: <path d="M7 5h11v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1h10v1a2 2 0 0 0 4 0M7 5a2 2 0 0 0-2 2v9m5-7h5m-5 3h5" />,
    material: <path d="M12 3l7 5-3 11H8L5 8zM5 8h14M12 3l-3 5 3 11 3-11z" />,
    recipe: <path d="M6 3h9l3 3v15H6zM9 9h6m-6 3h6m-6 3h4" />,
    arrow: <path d="M5 19L19 5m0 0h-5m5 0v5M5 19l1-4m-1 4l4-1M8 16l-2-2" />,
    adena: <path d="M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3v10m3-8h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" />,
    dye: <path d="M12 3c3 5 6 8 6 12a6 6 0 0 1-12 0c0-4 3-7 6-12z" />,
    seed: <path d="M6 18c0-8 5-12 13-12 0 8-4 13-13 12zm0 0l7-7" />,
    other: <path d="M4 8l8-4 8 4v8l-8 4-8-4zm0 0l8 4 8-4m-8 4v8" />,
};

const KIND_GLYPH: Record<string, string> = {
    sword: 'sword', ancientsword: 'sword', rapier: 'sword', etc: 'sword', flag: 'sword', ownthing: 'sword',
    dagger: 'dagger', dualdagger: 'dual', dual: 'dual', blunt: 'blunt', bow: 'bow', crossbow: 'bow',
    pole: 'pole', fishingrod: 'pole', fist: 'fist', dualfist: 'fist',
    chest: 'chest', fullarmor: 'chest', onepiece: 'chest', underwear: 'chest', alldress: 'chest', back: 'chest',
    legs: 'legs', head: 'head', gloves: 'gloves', feet: 'feet', shield: 'shield', sigil: 'sigil',
    neck: 'neck', 'rear;lear': 'ear', 'rfinger;lfinger': 'ring',
    potion: 'potion', elixir: 'potion', scroll: 'scroll', recipe: 'recipe', material: 'material', adena: 'adena',
    arrow: 'arrow', bolt: 'arrow', dye: 'dye', seed: 'seed', seed2: 'seed', crop: 'seed', maturecrop: 'seed',
};

function glyph(item: Pick<ItemSummary, 'kind' | 'category'>): string {
    if (KIND_GLYPH[item.kind]) {
        return KIND_GLYPH[item.kind];
    }

    if (item.kind.includes('scrl') || item.kind.includes('enchant')) {
        return 'scroll';
    }

    return item.category === 'accessory' ? 'accessory' : 'other';
}

export function ItemIcon({ item, size = 'md' }: { item: Pick<ItemSummary, 'name' | 'kind' | 'category' | 'grade' | 'icon'>; size?: 'sm' | 'md' | 'lg' }) {
    const grade = (item.grade ?? 'none').toLowerCase();

    if (item.icon) {
        return <img className={`item-icon item-icon-${size} grade-frame-${grade}`} src={item.icon} alt="" width={32} height={32} loading="lazy" />;
    }

    return (
        <span className={`item-icon item-icon-${size} grade-frame-${grade}`} aria-hidden="true">
            <svg viewBox="0 0 24 24">{GLYPHS[glyph(item)]}</svg>
        </span>
    );
}

export function GradeBadge({ grade }: { grade: string | null }) {
    return grade ? <span className={`grade grade-${grade.toLowerCase()}`}>{grade}</span> : null;
}

/** "Dagger", "Heavy chest"... for lists. */
const KIND_LABEL: Record<string, string> = {
    ancientsword: 'Ancient sword', dualdagger: 'Dual daggers', dual: 'Dual swords', dualfist: 'Fists', fist: 'Fist', pole: 'Polearm',
    fishingrod: 'Fishing rod', etc: 'Weapon', ownthing: 'Weapon', flag: 'Flag', chest: 'Upper body', legs: 'Lower body',
    fullarmor: 'Full armor', onepiece: 'Full armor', head: 'Helmet', feet: 'Boots', neck: 'Necklace', 'rear;lear': 'Earring',
    'rfinger;lfinger': 'Ring', hair: 'Hair accessory', hair2: 'Hair accessory', hairall: 'Hair accessory', deco1: 'Talisman',
    lbracelet: 'Bracelet', rbracelet: 'Bracelet', waist: 'Belt', back: 'Cloak', underwear: 'Shirt', alldress: 'Costume',
    other: 'Item', adena: 'Currency', castle_guard: 'Mercenary ticket', seed2: 'Seed', maturecrop: 'Crop', pet_collar: 'Pet collar',
    scrl_enchant_wp: 'Enchant scroll', scrl_enchant_am: 'Enchant scroll', bless_scrl_enchant_wp: 'Blessed enchant scroll',
    bless_scrl_enchant_am: 'Blessed enchant scroll', scrl_enchant_attr: 'Attribute scroll', scrl_inc_enchant_prop_wp: 'Enchant aid',
    scrl_inc_enchant_prop_am: 'Enchant aid', ancient_crystal_enchant_wp: 'Ancient enchant crystal', ancient_crystal_enchant_am: 'Ancient enchant crystal',
};

/** The same in Brazilian Portuguese; kinds missing here fall back to the English label. */
const KIND_LABEL_PT: Record<string, string> = {
    sword: 'Espada', ancientsword: 'Espada ancestral', rapier: 'Rapieira', dagger: 'Adaga', dualdagger: 'Adagas duplas', dual: 'Espadas duplas',
    blunt: 'Arma de impacto', dualblunt: 'Armas de impacto duplas', bow: 'Arco', crossbow: 'Besta', twohandcrossbow: 'Besta de duas mãos',
    pole: 'Arma de haste', fist: 'Soqueira', dualfist: 'Punhos', pistols: 'Pistolas', fishingrod: 'Vara de pesca', etc: 'Arma', ownthing: 'Arma',
    flag: 'Bandeira', chest: 'Parte superior', legs: 'Parte inferior', fullarmor: 'Armadura completa', onepiece: 'Armadura completa',
    head: 'Elmo', gloves: 'Luvas', feet: 'Botas', shield: 'Escudo', sigil: 'Sigilo', neck: 'Colar', 'rear;lear': 'Brinco', 'rfinger;lfinger': 'Anel',
    hair: 'Acessório de cabelo', hair2: 'Acessório de cabelo', hairall: 'Acessório de cabelo', deco1: 'Talismã', talisman: 'Talismã',
    lbracelet: 'Bracelete', rbracelet: 'Bracelete', waist: 'Cinto', back: 'Capa', underwear: 'Camisa', alldress: 'Traje', agathion: 'Agathion',
    brooch: 'Broche', other: 'Item', adena: 'Moeda', castle_guard: 'Bilhete de mercenário', seed: 'Semente', seed2: 'Semente', maturecrop: 'Colheita',
    pet_collar: 'Coleira de mascote', scrl_enchant_wp: 'Pergaminho de encantamento', scrl_enchant_am: 'Pergaminho de encantamento',
    bless_scrl_enchant_wp: 'Pergaminho de encantamento abençoado', bless_scrl_enchant_am: 'Pergaminho de encantamento abençoado',
    scrl_enchant_attr: 'Pergaminho de atributo', scrl_inc_enchant_prop_wp: 'Auxílio de encantamento', scrl_inc_enchant_prop_am: 'Auxílio de encantamento',
    ancient_crystal_enchant_wp: 'Cristal de encantamento ancestral', ancient_crystal_enchant_am: 'Cristal de encantamento ancestral',
    material: 'Material', recipe: 'Receita', potion: 'Poção', elixir: 'Elixir', scroll: 'Pergaminho', arrow: 'Flecha', bolt: 'Virote', dye: 'Tintura',
    herb: 'Erva', lure: 'Isca', harvest: 'Colheita', coupon: 'Cupom', lotto: 'Loteria', race_ticket: 'Bilhete de corrida', rune: 'Runa',
    soulshot: 'Soulshot', spiritshot: 'Spiritshot', crystal: 'Cristal', lifestone: 'Pedra da vida', enchant_attr_stone: 'Pedra de atributo',
};

export function kindLabel(kind: string, locale: 'en' | 'pt_BR' = 'en'): string {
    if (locale === 'pt_BR' && KIND_LABEL_PT[kind]) {
        return KIND_LABEL_PT[kind];
    }

    return KIND_LABEL[kind] ?? kind.charAt(0).toUpperCase() + kind.slice(1).replace(/_/g, ' ');
}
