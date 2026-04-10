# Whereabouts

**[⬇ Download Latest Version](https://github.com/djenders/whereabouts/releases/latest/download/whereabouts.zip)**

> Install this ZIP via **Plugins → Add New → Upload Plugin** in WordPress.

---

A WordPress plugin that adds a Gutenberg block rendering a natural-language sentence about your current location, time, and weather — fully customisable via a sentence template.

> *It's half past nine in [Milwaukee](/now), where it is overcast and 47°F.*

Or however you want to say it.

---

## Features

- **Sentence template** — arrange tokens however you like: `It's {condition} in {city} at {time}.`
- **Smart time formatting** — natural phrases at 5-minute marks (half past nine, quarter to three); exact digital fallback for everything else (9:37 pm)
- **Three time token styles** — default, short, and long
- **Open-Meteo weather** — free, no API key, real-time conditions
- **30-minute weather cache** — fast page loads, auto-cleared when settings change
- **City link** — `{city}` optionally links to your /now page
- **12 or 24-hour clock** — your preference
- **°F or °C** — your preference
- **Tag selector** — render as `<p>`, `<h1>`–`<h4>`, or `<span>` from the block toolbar
- **GitHub auto-updates** — updates appear in Dashboard → Updates automatically

---

## Tokens

### Time

| Token | Named phrase | Digital fallback |
|---|---|---|
| `{time}` | half past nine | 9:37 pm |
| `{time:short}` | half past nine | 9:37 |
| `{time:long}` | half past nine | 9:37 in the evening |

Natural phrases never include am/pm — they don't need it. The digital fallback respects your clock format setting (12 or 24-hour). Midnight and noon are always written as words.

**Period phrases** used by `{time:long}` on digital fallback:
- midnight to noon → *in the morning*
- noon to 6pm → *in the afternoon*
- 6pm to midnight → *in the evening*

### Place & Weather

| Token | Example output |
|---|---|
| `{city}` | Milwaukee (linked if /now URL is set) |
| `{condition}` | overcast |
| `{temp}` | 47°F or 47°C |
| `{temp:number}` | 47° |
| `{temp:long}` | 47 degrees |

### Example templates

```
It's {time} in {city}, where it is {condition} and {temp}.
→ It's half past nine in Milwaukee, where it is overcast and 47°F.

It's {condition} in {city} at {time:long}.
→ It's overcast in Milwaukee at 9:37 in the evening.

{city} — {condition}, {temp:long}.
→ Milwaukee — overcast, 47 degrees.
```

---

## Installation

1. Click **[⬇ Download Latest Version](https://github.com/djenders/whereabouts/releases/latest/download/whereabouts.zip)** above
2. In WordPress: **Plugins → Add New → Upload Plugin**
3. Upload the ZIP, activate — you'll land on the settings page automatically
4. Configure your location and template

> **Note:** Use the download link above, not the "Download ZIP" button on the main repo page — that creates the wrong folder structure.

---

## GitHub Auto-Updates

Whereabouts checks for updates directly from this GitHub repository. Updates appear in **Dashboard → Updates** just like any other plugin — no wordpress.org account required.

To publish an update, create a new GitHub Release with a version tag (e.g. `v1.3.0`). The tag must match the version in the plugin header.

---

## Styling

The block renders with the class `whereabouts-sentence`. Style it in your theme:

```css
.whereabouts-sentence {
    font-size: 1.2em;
    font-style: italic;
    color: #555;
}

.whereabouts-city-link {
    /* city link styles — inherits color by default */
}
```

---

## License

GPL-2.0-or-later
