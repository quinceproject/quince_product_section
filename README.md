# Quince Product Sections

**"Recommended" rows of products under every product list for PrestaShop 9.2.**

Quince Product Sections adds rows of products under the product list on the category,
brand, search results, best sellers, new products, promotions and supplier pages.
Each of these pages gets its own list of sections, and each section decides how it
picks products, how many it shows and what its heading says. The rows use the same
product cards and carousel as the rest of the Quince theme.

> Quince Product Sections is a free, open-source module (OSL-3.0). There is nothing
> to buy. If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Seven pages, set up separately.** Category, brand, search results, best sellers,
  new products, promotions and supplier. A page can have several sections, shown one
  under another in the order the shop drags them into.
- **Eight ways to pick products.**
  - Best selling, newest, random, cheapest first and most expensive first.
  - Recently viewed by the customer, from the cookie kept by the PrestaShop
    `ps_viewedproduct` module. A customer who has not viewed anything yet sees no
    row, rather than products presented as "recently viewed" they never saw.
  - Most searched, ranked by
    [Quince Search Score](https://github.com/quinceproject/quince_search_score)
    when that module is installed.
  - Hand-picked: product numbers typed in the order they should appear.
- **On the subject of the page.** On the category and brand pages the rows pick from
  that category or brand, optionally with subcategories. The other pages have no
  catalogue of their own, so their rows pick from the whole shop.
- **No repeats.** A section can leave out products already shown on the list above
  it.
- **No half-empty rows.** A minimum number of products below which the row does not
  show at all.
- **Heading, icon and arrows.** A heading per language (default "Recommended"), an
  icon next to it and scrolling arrows on wide screens. On a phone the row is swiped
  by finger.
- **Only products the shop shows.** Active products visible in the catalogue.
- **Multilingual.** English base with a Polish translation, on PrestaShop's standard
  translation system.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| Quince theme | required for the rows to show |
| Quince Search Score | optional, for "Most searched" |
| `ps_viewedproduct` | optional, for "Recently viewed" |

Quince Engine places the spot for the rows under the product list, hosts the
settings screen and provides the icons; the Quince theme draws the carousel and the
product cards. The module declares Quince Engine as a dependency, so PrestaShop
refuses to install it without the engine.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_product_section.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_product_section/releases)
   page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_product_section` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince Product Sections and click
   **Install**.

**From Quince Engine** *(coming soon)*

The module list in Quince Engine will install the module straight from its release,
without downloading anything by hand.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/product-section` will work once the
package is published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_product_section/releases).

## Configuration

Open **Quince project → Product sections** in the back office menu.

1. Switch on the pages that should show rows.
2. On each page, **Add a section**, choose how products are picked and how many, and
   set the heading, the icon and the other options. Drag sections to reorder them.
3. Save.

Where the rows stand within the area under the list is set on the Quince Engine
**Product lists** screen, together with the other elements of the listing.

## Architecture

- **`displayQuinceListingSection`**: the module creates this hook and answers it.
  Quince Engine calls it from the area under the product list and passes the list and
  the category or brand.
- **`SectionProvider`** picks product numbers per section; the products are then
  presented the same way as on the product list.
- **`SectionSettings`** stores each page's sections as JSON in PrestaShop
  configuration under the `QUINCE_PSECTION_` prefix. The module creates no tables
  of its own.
- **Symfony controller and Twig** for the settings screen, inside the Quince Engine
  back office shell. The module announces itself to the engine through
  `actionQuinceRegisterFeature`.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_product_section.git
cd quince_product_section
composer install
```

The folder must be called `quince_product_section`, the same as the module. A clone
of this repository already has that name.

To build an installable archive, zip the `quince_product_section/` folder **with its
`vendor/` directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
