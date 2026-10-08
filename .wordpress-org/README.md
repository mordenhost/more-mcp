# WordPress.org directory assets

These are **not** shipped inside the plugin zip. WordPress.org reads them from a
top-level `assets/` directory in the plugin's SVN repository — a sibling of
`trunk/`, not a folder inside the plugin — so `build.sh` deliberately excludes
this whole directory.

## Files

| File | Where it appears |
|---|---|
| `icon-256x256.png` | Plugin directory search results, the Add Plugins screen, and the dashboard updates list |
| `icon-128x128.png` | Served directly at small sizes rather than downscaled on the fly |
| `banner-772x250.png` | Header of the plugin page on the directory |
| `banner-1544x500.png` | Same, on high-DPI displays |

## Deploying

Copy them into the SVN checkout's `assets/` directory, then commit:

```
svn co https://plugins.svn.wordpress.org/more-mcp/ more-mcp-svn
cp .wordpress-org/*.png more-mcp-svn/assets/
cd more-mcp-svn && svn add --force assets/ && svn ci -m "Update directory assets"
```

The directory picks up changes within a few minutes. Note that WordPress.org
caches icons aggressively at the CDN, so a replaced icon can take hours to appear
for everyone.

## Editing

The PNGs are rendered from the SVG sources in `src/`. Edit the SVG, re-render, and
commit both — the SVG is the source of truth and the PNG is a build artifact that
happens to be committed so nobody needs a renderer to deploy.

Rendered with the `nakkas` MCP server (`render_svg` / `preview`). Any SVG
rasterizer produces the same result; the only requirements are that the output
matches the exact pixel dimensions in each filename — WordPress.org rejects
mismatched sizes — and that comments contain no double hyphen, which that renderer
rejects.

## Design notes

The palette is Mordenhost's, read from `mordenhost.com`'s own CSS custom
properties rather than eyeballed:

| Token | Value | Use here |
|---|---|---|
| `--ink` | `#111111` | Icon ground; the mark on the banners |
| `--yellow` | `#ffd21e` | The mark on the icon; banner ground |
| `--on-yellow` | `#4a3d00` | Subtitle copy and offset shadows on yellow |
| `--radius-pill` | `999px` | Capability chips |
| `--radius-card` | `18px` | The node card on the wide banner |

The house style is neo-brutalist: 2px ink outlines, hard offset shadows
(`6px 6px 0`, never blurred), pill and 18px radii, no gradients anywhere, Plus
Jakarta Sans at weight 800 for display. An earlier pass used a blue gradient
system, which is precisely what this brand does not do — if these assets are ever
revised, adding a gradient is the first thing to reject.

The banners invert the icon: yellow ground with an ink mark, mirroring how
Mordenhost's own `.btn-yellow` and `.btn-ink` invert against each other. That also
buys a practical advantage — the WordPress.org directory is close to uniformly
blue-and-white, so a saturated yellow banner is the one that gets noticed in a grid
of search results.

### The mark

A **shield containing a hub with three satellites**: many AI clients converging on
one WordPress site, inside a container that says the connection is guarded.
Security-first is the plugin's actual differentiator — 41% of public MCP servers
ship with no authentication at all — so the shield carries meaning rather than
being a decorative frame.

Three decisions worth preserving if this is ever revised:

**The connector is drawn on the shield, not cut out of it.** An earlier version
masked the hub-and-satellites shape out of the shield so the ground showed through.
It looked cleaner at full size and failed at icon size: a cutout wide enough to
stay open at 24px is also wide enough to sever the shield into disconnected wedges,
so the container stopped reading as a shield at exactly the size that matters most.

**Three satellites, not four.** An even count reads as a plus sign or an X.

**Stroke weights are heavier than they look correct at 256px.** Hairlines are the
first thing to disappear when WordPress renders the icon at 24px in the updates
list, so the geometry is deliberately over-weighted. Verify any change by
downscaling to 24px and confirming the hub, the three satellites, and the
connectors still read as separate elements.

### Two banner sizes, authored separately

The 772 version is not a downscale of the 1544. A straight 50% reduction would put
the capability chips at ~10px and the node card's client labels at ~7px, both below
the legibility floor. So the small banner drops the card, keeps one chip (the
authentication one, since that is the differentiator), and re-proportions the
remaining lines.

In both, the mark is vertically centered on the **text block** rather than the
canvas, because the subtitle and chips hang below the wordmark and canvas-centering
makes the pair look misaligned. Both are verified by measuring rendered pixel
extents, not by eye.

