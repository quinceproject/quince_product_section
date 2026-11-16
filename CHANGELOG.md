# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.3] - 2026-11-16

### Changed

- New module logo: the letter Q in the project gradient.
- Files in `src/` stop when opened directly instead of through PrestaShop.

## [0.1.2] - 2026-11-15

### Security

- Every back-office screen of the module checks the employee's permissions. Before,
  any logged-in employee, whatever their profile, could open and save the module's
  settings. The screens now belong to the module's own menu tab
  (`AdminQuinceProductSection`): grant it to a profile under **Advanced Parameters → Team →
  Permissions**. SuperAdmin needs nothing. A profile that had access through the hidden
  `AdminQuince` tab needs access to this tab now.

## [0.1.1] - 2026-11-10

### Changed

- Polish translation completed: the texts added since the previous translation are now translated.
- Texts no longer use long dashes.

## [0.1.0] - 2026-11-10

First public release.

### Added

- Rows of products under the product list on seven pages: category, brand, search
  results, best sellers, new products, promotions and supplier, each with its own
  list of sections.
- Eight ways to pick products: best selling, newest, random, cheapest first, most
  expensive first, recently viewed by the customer, most searched (with Quince Search
  Score) and hand-picked.
- Per section: number of products (1 to 24), a minimum below which the row does not
  show, leaving out products already on the list, subcategories on the category
  page, a heading per language, an icon and scrolling arrows.
- Polish translation.
