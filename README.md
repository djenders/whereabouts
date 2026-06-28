# Whereabouts

**[⬇ Download Latest Version](https://github.com/djenders/whereabouts/releases/latest/download/whereabouts.zip)**

> Install this ZIP via **Plugins → Add New → Upload Plugin** in WordPress.

---

A WordPress plugin that adds a Gutenberg block and shortcode suite for rendering your current location, time, and weather — fully customisable.

> *It's half past nine in [Milwaukee](/now), where it is overcast and 47°F.*

Or however you want to say it.

---

## Features

- **Sentence template block** — arrange tokens however you like in a Gutenberg block
- **Shortcodes** — drop individual data points anywhere WordPress renders text
- **Smart time formatting** — natural phrases at 5-minute marks; digital fallback for everything else
- **Classic cartographic coordinates** — 43°02′20″ N, 87°54′24″ W
- **Open-Meteo weather** — free, no API key required
- **30-minute weather cache** — fast page loads, auto-cleared when settings change
- **City geocoder** — with pick list for ambiguous results (Paris, TX vs Paris, France)
- **12 or 24-hour clock** — your preference
- **°F or °C** — your preference
- **Tag selector** — render as `<p>`, `<h1>`–`<h4>`, or `<span>` from the block toolbar
- **GitHub auto-updates** — updates appear in Dashboard → Updates automatically

---

## Block Tokens

Use these in the sentence template on the settings page.

### Time

| Token | Named phrase | Digital fallback |
|---|---|---|
| `{time}` | half past nine | 9:37 pm |
| `{time:short}` | half past nine | 9:37 |
| `{time:long}` | half past nine | 9:37 in the evening |

Natural phrases never include am/pm. Midnight and noon are always written as words. The digital fallback respects your 12/24-hour setting.

**Period phrases** (used by `{time:long}` on digital fallback):
- midnight to noon → *in the morning*
- noon to 6pm → *in the afternoon*
- 6pm to midnight → *in the evening*

### Place, Weather & Coordinates

| Token | Example output |
|---|---|
| `{city}` | Milwaukee (linked if /now URL is set) |
| `{condition}` | overcast |
| `{temp}` | 47°F or 47°C |
| `{temp:number}` | 47° |
| `{temp:long}` | 47 degrees |
| `{coords}` | 43°02′20″ N, 87°54′24″ W |
| `{coords:dms}` | 43°02′20″ N, 87°54′24″ W |
| `{coords:decimal}` | 43.0389, -87.9065 |

### Example templates

```
It's {time} in {city}, where it is {condition} and {temp}.
→ It's half past nine in Milwaukee, where it is overcast and 47°F.

It's {condition} in {city} at {time:long}.
→ It's overcast in Milwaukee at 9:37 in the evening.

{coords} · {condition}, {temp:long}
→ 43°02′20″ N, 87°54′24″ W · overcast, 47 degrees
```

---

## Shortcodes

Use these anywhere WordPress renders text — posts, pages, widgets, theme templates.

| Shortcode | Example output |
|---|---|
| `[whereabouts time]` | half past nine *(or 9:37 pm)* |
| `[whereabouts time="short"]` | half past nine *(or 9:37)* |
| `[whereabouts time="long"]` | half past nine *(or 9:37 in the evening)* |
| `[whereabouts condition]` | overcast |
| `[whereabouts temp]` | 47°F |
| `[whereabouts temp="number"]` | 47° |
| `[whereabouts temp="long"]` | 47 degrees |
| `[whereabouts city]` | Milwaukee (linked if /now URL is set) |
| `[whereabouts coords]` | 43°02′20″ N, 87°54′24″ W |
| `[whereabouts coords="decimal"]` | 43.0389, -87.9065 |
| `[whereabouts coords="dms"]` | 43°02′20″ N, 87°54′24″ W |

Each shortcode wraps its output in a `<span>` with a CSS class (e.g. `whereabouts-time`, `whereabouts-coords`) for styling.

---

## Installation

1. Click **[⬇ Download Latest Version](https://github.com/djenders/whereabouts/releases/latest/download/whereabouts.zip)** above
2. In WordPress: **Plugins → Add New → Upload Plugin**
3. Upload the ZIP, activate — you'll land on the settings page automatically
4. Configure your location and template

> **Note:** Use the download link above, not the "Download ZIP" button on the main repo page — that creates the wrong folder structure.

---

## GitHub Auto-Updates

Whereabouts checks for updates directly from this GitHub repository. Updates appear in **Dashboard → Updates** just like any other plugin.

To publish an update, create a new GitHub Release with a version tag (e.g. `v1.4.0`). The tag must match the version in the plugin header.

---

## Styling

```css
/* Block sentence wrapper */
.whereabouts-sentence { }

/* City link */
.whereabouts-city-link {
    text-decoration: underline;
    text-decoration-style: dotted;
    color: inherit;
}

/* Shortcode spans */
.whereabouts-time      { }
.whereabouts-condition { }
.whereabouts-temp      { }
.whereabouts-city      { }
.whereabouts-coords    { }
```

---

## License

GPL-2.0-or-later
