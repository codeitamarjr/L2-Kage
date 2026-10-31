#!/usr/bin/env python3
"""L-Coin Store: keeps the client's product list in step with the server's.

overrides/game/data/LimitShop.xml is the source of truth for what is sold, prices and limits.
cards.json says how a product looks in the client (tab, ribbon, main page slot).
See docs/LCOIN-STORE.md for the full workflow.

    lcoin_store.py check --client DIR    validate the XML, cards and items
    lcoin_store.py build --client DIR --out DIR --for-testing    also write the product list for each language (the client rejects these)
    lcoin_store.py show ITEM... --client DIR    show what an item is on both sides

--client is a folder holding copies of these client files, by language:
    <lang>/LCoinShopProduct_ClassicAden-<lang>.dat, <lang>/ItemName_ClassicAden-<lang>.dat, <lang>/L2GameDataName.dat
"""
import argparse
import glob
import json
import os
import struct
import sys
import xml.etree.ElementTree as ET
import zlib

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SHOP_XML = os.path.join(ROOT, "overrides/game/data/LimitShop.xml")
ITEMS_DIR = os.path.join(ROOT, "L2J_Mobius_Essence_10.1_SamuraiCrow/dist/game/data/stats/items")
ITEM_OVERRIDES_DIR = os.path.join(ROOT, "overrides/game/data/stats/items")
MULTISELL_DIR = os.path.join(ROOT, "L2J_Mobius_Essence_10.1_SamuraiCrow/dist/game/data/multisell/items")
HANDLERS_DIR = os.path.join(ROOT, "L2J_Mobius_Essence_10.1_SamuraiCrow/dist/game/data/scripts/handlers/items")
CARDS_JSON = os.path.join(os.path.dirname(os.path.abspath(__file__)), "cards.json")

LANGS = ("eu", "es", "pl")
HEADER = "Lineage2Ver413".encode("utf-16-le")
# The public half of the key the client's own files are signed with (L2ClientDat's v413_original). Read-only.
RSA_N = int("97df398472ddf737ef0a0cd17e8d172f0fef1661a38a8ae1d6e829bc1c6e4c3cfc19292dda9ef90175e46e7394a18850b6417d03be6eea274d3ed1dde5b5d7bde72cc0a0b71d03608655633881793a02c9a67d9ef2b45eb7c08d4be329083ce450e68f7867b6749314d40511d09bc5744551baa86a89dc38123dc1668fd72d83", 16)
RSA_E = 0x35

MARKS = ["none", "event", "sale", "best", "limited", "new", "relay", "max"]
RESETS = ["always", "one_day", "one_week", "one_month"]
LIMIT_ATTRS = {"accountBuyLimit": 0, "accountDailyLimit": 1, "accountWeeklyLimit": 2, "accountMonthlyLimit": 3}
CURRENCIES = {57: "Adena", 91663: "L-Coin", 97145: "Ancient Adena"}


def read_dat(path):
    """Returns the payload of a client file: signed by the client's key, or already plain."""
    raw = open(path, "rb").read()
    if raw[:28] != HEADER:
        return raw
    body = raw[28:-20]
    out = bytearray()
    for i in range(0, len(body), 128):
        chunk = pow(int.from_bytes(body[i:i + 128], "big"), RSA_E, RSA_N).to_bytes(128, "big")
        size = int.from_bytes(chunk[:4], "big")
        pad = -size & 3
        out += chunk[128 - size - pad:128 - pad]
    return zlib.decompress(bytes(out[4:]))


class Reader:
    def __init__(self, data):
        self.b, self.p = data, 0

    def take(self, fmt):
        value = struct.unpack_from("<" + fmt, self.b, self.p)[0]
        self.p += struct.calcsize("<" + fmt)
        return value

    def cint(self):
        first = self.take("B")
        value, shift = first & 0x3F, 6
        if first & 0x40:
            while True:
                x = self.take("B")
                value |= (x & 0x7F) << shift
                shift += 7
                if not x & 0x80:
                    break
        return -value if first & 0x80 else value

    def ascf(self):
        n = self.cint()
        if n >= 0:
            raw = self.b[self.p:self.p + n]
            self.p += n
            return raw.rstrip(b"\0").decode("latin-1")
        raw = self.b[self.p:self.p - 2 * n]
        self.p -= 2 * n
        return raw.decode("utf-16-le").rstrip("\0")


def write_cint(value):
    v = abs(value)
    first = v & 0x3F
    v >>= 6
    first |= (0x80 if value < 0 else 0) | (0x40 if v else 0)
    out = bytearray([first])
    while v:
        x = v & 0x7F
        v >>= 7
        out.append(x | 0x80 if v else x)
    return bytes(out)


def write_ascf(text):
    if not text:
        return write_cint(0)
    try:
        raw = text.encode("latin-1") + b"\0"
        return write_cint(len(raw)) + raw
    except UnicodeEncodeError:
        raw = text.encode("utf-16-le") + b"\0\0"
        return write_cint(-(len(raw) // 2)) + raw


def parse_products(data):
    r = Reader(data)
    products = []
    for _ in range(r.take("I")):
        p = {"id": r.take("I"), "category": r.take("B"), "mark": r.take("B"), "items": []}
        for _ in range(r.take("B")):
            p["items"].append({"item": r.take("I"), "count": r.take("I"), "name": r.ascf(), "min": r.take("H"), "max": r.take("H")})
        p.update(type=r.take("B"), limit_type=r.take("B"), reset=r.take("B"), limit=r.take("I"), desc=r.ascf(), htm=r.ascf())
        products.append(p)
    return products, data[r.p:]


def build_products(products, tail):
    out = bytearray(struct.pack("<I", len(products)))
    for p in products:
        out += struct.pack("<IBBB", p["id"], p["category"], p["mark"], len(p["items"]))
        for it in p["items"]:
            out += struct.pack("<II", it["item"], it["count"]) + write_ascf(it["name"]) + struct.pack("<HH", it["min"], it["max"])
        out += struct.pack("<BBBI", p["type"], p["limit_type"], p["reset"], p["limit"]) + write_ascf(p["desc"]) + write_ascf(p["htm"])
    return bytes(out) + tail


def client_items(folder, lang):
    """{item id: {name, add, desc}} from ItemName, whose names live in L2GameDataName."""
    data = read_dat(os.path.join(folder, lang, "L2GameDataName.dat"))
    count, p = struct.unpack_from("<I", data, 0)[0], 4
    names = []
    for _ in range(count):
        size = struct.unpack_from("<I", data, p)[0]
        names.append(data[p + 4:p + 4 + size].decode("utf-16-le").rstrip("\0"))
        p += 4 + size
    r = Reader(read_dat(os.path.join(folder, lang, f"ItemName_ClassicAden-{lang}.dat")))
    items = {}
    for _ in range(r.take("I")):
        item_id, name = r.take("I"), r.take("i")
        add, desc = r.ascf(), r.ascf()
        r.p += 2 + 4 + 4 + 2 + 1 + 16 + 7 + 4 + 1 + 1 + 4
        items[item_id] = {"name": names[name] if 0 <= name < count else "", "add": add, "desc": desc}
    return items


def server_items():
    items = {}
    # Overrides replace upstream's file when the server starts, so they are read last.
    for path in glob.glob(os.path.join(ITEMS_DIR, "*.xml")) + glob.glob(os.path.join(ITEM_OVERRIDES_DIR, "*.xml")):
        for node in ET.parse(path).getroot().findall("item"):
            sets = {s.get("name"): s.get("val") for s in node.findall("set")}
            items[int(node.get("id"))] = {
                "name": node.get("name"),
                "handler": sets.get("handler"),
                "action": sets.get("default_action"),
                "skills": len(node.findall("skills/skill")),
                "contents": [(int(x.get("id")), x.get("min"), x.get("max"), x.get("chance")) for x in node.findall("capsuled_items/item")],
            }
    return items


def multisell_ingredients():
    ids = set()
    for path in glob.glob(os.path.join(MULTISELL_DIR, "*.xml")):
        ids.update(int(x.get("id")) for x in ET.parse(path).getroot().iter("ingredient"))
    return ids


def usable_problem(item_id, server, client, handlers, multisell):
    """Why a buyer could not use this item, or None. Coupons open a multisell the client asks for, so have no handler."""
    s, c = server.get(item_id), client.get(item_id)
    if not s:
        return "not in the server's item data"
    if not c:
        return "not in the client's item list, so it would show blank"
    if "not available" in (c["add"] or "").lower():
        return "the client marks it Not available"
    if s["handler"] and s["handler"] not in handlers:
        return f"handler {s['handler']} does not exist"
    if s["handler"] == "ExtractableItems" and not s["contents"]:
        return "is an empty box"
    if s["handler"] == "ItemSkills" and not s["skills"]:
        return "has no skill to use"
    if "double-click" in c["desc"].lower() and not s["handler"] and item_id not in multisell:
        return "says double-click but nothing handles it"
    return None


def card_name(item_id, count, client):
    c = client.get(item_id, {})
    name = c.get("name") or f"Item {item_id}"
    if c.get("add"):
        name += f" ({c['add']})"
    return f"{name} x{count}" if count > 1 else name


def load_shop():
    products = []
    for node in ET.parse(SHOP_XML).getroot().findall("product"):
        prod = node.find("production")
        limits = {k: int(v) for k, v in prod.attrib.items() if k in LIMIT_ATTRS}
        products.append({
            "id": int(node.get("id")),
            "category": int(node.get("category")),
            "min": int(node.get("minLevel", 1)),
            "max": int(node.get("maxLevel", 999)),
            "price": [(int(i.get("id")), int(i.get("count", 1))) for i in node.findall("ingredient")],
            "item": int(prod.get("id")),
            "count": int(prod.get("count", 1)),
            "limits": limits,
        })
    return products


def merge(shop, original, cards, client):
    """The new product list for one language; returns it with the limit labels that changed."""
    by_id = {p["id"]: p for p in original}
    relabelled = []
    built = {}
    for s in shop:
        look = cards.get(str(s["id"]), {})
        old = by_id.get(s["id"])
        card = dict(old) if old else {"id": s["id"], "category": s["category"], "mark": 0, "type": 0, "desc": "", "htm": ""}
        if "category" in look:
            card["category"] = look["category"]
        if "mark" in look:
            card["mark"] = MARKS.index(look["mark"])
        if "type" in look:
            card["type"] = look["type"]
        same_item = old and len(old["items"]) == 1 and old["items"][0]["item"] == s["item"] and old["items"][0]["count"] == s["count"]
        name = old["items"][0]["name"] if same_item else card_name(s["item"], s["count"], client)
        card["items"] = [{"item": s["item"], "count": s["count"], "name": name, "min": s["min"], "max": s["max"]}]
        attr, value = next(iter(s["limits"].items()), (None, 0))
        limit = (2, LIMIT_ATTRS[attr], value) if attr else (0, 0, 0)
        if old and (old["limit_type"], old["reset"], old["limit"]) != limit:
            relabelled.append(s["id"])
        card["limit_type"], card["reset"], card["limit"] = limit
        built[s["id"]] = card
    order = [int(k) for k in cards if int(k) in built]
    rest = [p["id"] for p in original if p["id"] in built and p["id"] not in order]
    extra = [s["id"] for s in shop if s["id"] not in order and s["id"] not in rest]
    unsold = [p for p in original if p["id"] not in built]
    return [built[i] for i in order + rest + extra] + unsold, relabelled


def check(args):
    shop = load_shop()
    cards = json.load(open(CARDS_JSON))["cards"]
    server, multisell = server_items(), multisell_ingredients()
    handlers = {os.path.splitext(f)[0] for f in os.listdir(HANDLERS_DIR)}
    errors, warnings = [], []

    seen = set()
    for s in shop:
        if s["id"] in seen:
            errors.append(f"product {s['id']}: id used twice")
        seen.add(s["id"])
    for key in cards:
        if int(key) not in seen:
            errors.append(f"cards.json: {key} is not in LimitShop.xml")
        if cards[key].get("mark", "none") not in MARKS:
            errors.append(f"cards.json: {key} has an unknown mark")

    # Limits are counted per item, not per product, so one item needs one limit.
    by_item = {}
    for s in shop:
        by_item.setdefault(s["item"], []).append(s)
    for item, group in by_item.items():
        if len({tuple(sorted(g["limits"].items())) for g in group}) > 1:
            warnings.append(f"item {item} is sold with different limits by products {[g['id'] for g in group]}; they share one counter")

    result = {}
    for lang in LANGS:
        original, tail = parse_products(read_dat(os.path.join(args.client, lang, f"LCoinShopProduct_ClassicAden-{lang}.dat")))
        client = client_items(args.client, lang)
        if lang == "eu":
            for s in shop:
                problem = usable_problem(s["item"], server, client, handlers, multisell)
                if problem:
                    errors.append(f"product {s['id']} sells item {s['item']}: {problem}")
                for currency, count in s["price"]:
                    if currency not in server:
                        errors.append(f"product {s['id']}: price item {currency} does not exist")
                    if count <= 0:
                        errors.append(f"product {s['id']}: price must be above 0")
                # A box is only worth what is inside it, so look all the way down.
                todo, opened = [c[0] for c in server.get(s["item"], {}).get("contents", [])], set()
                while todo:
                    inner = todo.pop()
                    if inner in opened:
                        continue
                    opened.add(inner)
                    problem = usable_problem(inner, server, client, handlers, multisell)
                    if problem:
                        warnings.append(f"product {s['id']}: inside the box, item {inner} {client.get(inner, {}).get('name')}: {problem}")
                    todo.extend(c[0] for c in server.get(inner, {}).get("contents", []))
        else:
            missing = [s["id"] for s in shop if s["item"] not in client]
            if missing:
                errors.append(f"{lang}: no client name for products {missing}")
        merged, relabelled = merge(shop, original, cards, client)
        payload = build_products(merged, tail)
        if parse_products(payload)[0] != merged:
            errors.append(f"{lang}: the built file does not read back the same")
        if build_products(original, tail) != read_dat(os.path.join(args.client, lang, f"LCoinShopProduct_ClassicAden-{lang}.dat")):
            warnings.append(f"{lang}: the original does not rebuild byte for byte, so its layout may have changed")
        result[lang] = (merged, payload, relabelled)

    sold = len(shop)
    new = [s["id"] for s in shop if s["id"] not in {p["id"] for p in parse_products(read_dat(os.path.join(args.client, 'eu', 'LCoinShopProduct_ClassicAden-eu.dat')))[0]}]
    print(f"{sold} products on sale, {len(cards)} with a look in cards.json, {len(new)} new to the client, limit labels corrected: {len(result['eu'][2])}")
    for w in warnings:
        print("warning:", w)
    for e in errors:
        print("ERROR:", e)
    return errors, result


def cmd_check(args):
    errors, _ = check(args)
    sys.exit(1 if errors else 0)


def cmd_build(args):
    if not args.for_testing:
        sys.exit("build: the client rejects these files as corrupt and won't start (docs/LCOIN-STORE.md). Pass --for-testing only for a test client.")
    errors, result = check(args)
    if errors:
        sys.exit(1)
    for lang, (_, payload, _) in result.items():
        folder = os.path.join(args.out, lang)
        os.makedirs(folder, exist_ok=True)
        path = os.path.join(folder, f"LCoinShopProduct_ClassicAden-{lang}.dat")
        open(path, "wb").write(payload)
        print("wrote", path, len(payload), "bytes")


def cmd_show(args):
    server, multisell = server_items(), multisell_ingredients()
    handlers = {os.path.splitext(f)[0] for f in os.listdir(HANDLERS_DIR)}
    client = client_items(args.client, "eu")
    for item in args.items:
        i = int(item)
        s, c = server.get(i, {}), client.get(i, {})
        print(f"{i}  client: {c.get('name')} [{c.get('add')}]  server: {s.get('name')}  handler: {s.get('handler')}")
        print("  problem:", usable_problem(i, server, client, handlers, multisell))
        print("  text:", (c.get("desc") or "")[:160].replace("\\n", " "))
        for content in s.get("contents", []):
            print(f"    {content[0]} {client.get(content[0], {}).get('name')} x{content[1]}-{content[2]} {content[3]}%")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="command", required=True)
    for name, func in (("check", cmd_check), ("build", cmd_build), ("show", cmd_show)):
        cmd = sub.add_parser(name)
        cmd.add_argument("--client", required=True)
        cmd.set_defaults(func=func)
        if name == "build":
            cmd.add_argument("--out", required=True)
            cmd.add_argument("--for-testing", action="store_true")
        if name == "show":
            cmd.add_argument("items", nargs="+")
    args = parser.parse_args()
    args.func(args)


if __name__ == "__main__":
    main()
