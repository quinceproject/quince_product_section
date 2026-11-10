<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use Quince\Engine\Ui\IconLibrary;
use Quince\ProductSection\Products\SectionProvider;
use Quince\ProductSection\Settings\SectionSettings;

/**
 * Quince Product Sections — sekcja „polecane" pod listą produktów.
 *
 * Siedem stron z listą produktów ma siedem osobnych ustawień, ale jedno
 * miejsce: element „Polecane produkty" w strefie pod listą, który sklep
 * ustawia tam, gdzie chce, razem z resztą elementów listingu. Moduł poznaje
 * stronę po kontrolerze, który ją wyświetla.
 *
 * Dobór produktów jest w `SectionProvider`, wygląd bierze z motywu: ta sama
 * karuzela i ta sama zajawka co akcesoria i produkty do dokupienia.
 */
class Quince_Product_Section extends Module
{
    private const DOMENA_PANELU = 'Modules.Quinceproductsection.Admin';
    private const DOMENA_SKLEPU = 'Modules.Quinceproductsection.Shop';

    /** Miejsce w strefie pod listą produktów, stawiane przez silnik. */
    public const HOOK_SECTION = 'displayQuinceListingSection';

    public function __construct()
    {
        $this->name = 'quince_product_section';
        $this->tab = 'front_office_features';
        $this->version = '0.1.1';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];

        // Silnik nie jest opcjonalny: to on stawia miejsce w strefie pod listą,
        // on daje ekran ustawień, a jego motyw rysuje karuzelę.
        $this->dependencies = ['quince_engine'];

        $this->tabs = [
            [
                'class_name' => 'AdminQuinceProductSection',
                'visible' => true,
                'name' => 'Product sections',
                'parent_class_name' => 'AdminQuinceProject',
                'route_name' => 'quince_product_section_settings',
                'wording' => 'Product sections',
                'wording_domain' => self::DOMENA_PANELU,
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince Product Sections', [], self::DOMENA_PANELU);
        $this->description = $this->trans(
            'A "Recommended" row of products under the product list, set up separately for the category, brand, search, best sellers, new products, promotions and supplier pages. Requires Quince Engine.',
            [],
            self::DOMENA_PANELU
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->zalozHook()
            && $this->registerHook(['actionQuinceRegisterFeature', self::HOOK_SECTION])
            && $this->installModuleGroups()
            && $this->ustawieniaPoczatkowe();
    }

    public function uninstall(): bool
    {
        foreach ((new SectionSettings())->configurationKeys() as $klucz) {
            Configuration::deleteByName($klucz);
        }

        return parent::uninstall();
    }

    /**
     * Ogłoszenie własnego miejsca.
     *
     * `Hook::add()` przed rejestracją: PrestaShop po cichu pomija rejestrację
     * na hook, którego nie ma jeszcze w tabeli.
     */
    private function zalozHook(): bool
    {
        if (Hook::getIdByName(self::HOOK_SECTION)) {
            return true;
        }

        $hook = new Hook();
        $hook->name = self::HOOK_SECTION;
        $hook->title = 'Quince: polecane produkty pod listą';
        $hook->description = 'Rząd produktów pod listą na stronie kategorii, marki, wyników wyszukiwania,'
            . ' bestsellerów, nowości, promocji i dostawcy. Miejsce stawia element listingu w panelu silnika.';
        $hook->position = false;

        return (bool) $hook->add();
    }

    /**
     * Wpisy w ps_module_group.
     *
     * Bez nich hook frontendowy w ogóle nie odpala: PrestaShop dokłada do listy
     * modułów warunek na grupę klienta i moduł bez wpisów znika z wyników.
     */
    private function installModuleGroups(): bool
    {
        $grupy = Group::getGroups((int) Context::getContext()->language->id, true);

        foreach ($grupy as $grupa) {
            $wiersz = [
                'id_module' => (int) $this->id,
                'id_shop' => (int) Context::getContext()->shop->id,
                'id_group' => (int) $grupa['id_group'],
            ];

            if (!Db::getInstance()->insert('module_group', $wiersz, false, true, Db::INSERT_IGNORE)) {
                return false;
            }
        }

        return true;
    }

    private function ustawieniaPoczatkowe(): bool
    {
        (new SectionSettings())->seed();

        return true;
    }

    /**
     * Zgłoszenie do rejestru możliwości silnika.
     *
     * Stąd bierze się pozycja „Product sections" w panelu Quince: silnik
     * pokazuje w nawigacji wyłącznie moduły, które same się zgłosiły, więc
     * odinstalowanie modułu nie zostawia po sobie martwego odnośnika.
     *
     * @return array<int,array<string,string>>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'type' => 'product_section',
            'id' => 'quince_product_section',
            'label' => $this->trans('Product sections', [], self::DOMENA_PANELU),
            'route' => 'quince_product_section_settings',
        ]];
    }

    /**
     * Sekcje pod listą produktów.
     *
     * Silnik woła ten hook z elementu listingu i podaje mu listę, pod którą
     * sekcje stoją, oraz numer kategorii albo marki — żeby moduł nie musiał
     * sięgać do pól kontrolera, które są chronione. Strona może mieć więcej
     * niż jedną sekcję: każda renderuje się osobno i staje jedna pod drugą,
     * w kolejności, w jakiej sklep je ustawił.
     *
     * @param array<string,mixed> $params
     */
    public function hookDisplayQuinceListingSection(array $params): string
    {
        $ustawienia = new SectionSettings();
        $strona = $ustawienia->pageByController((string) ($this->context->controller->php_self ?? ''));

        if ($strona === null) {
            return '';
        }

        $cfg = $ustawienia->page($strona);

        if (!$cfg['enabled']) {
            return '';
        }

        $html = '';

        foreach ($cfg['sections'] as $sekcja) {
            if (!$sekcja['active']) {
                continue;
            }

            $html .= $this->renderujSekcje($strona, $sekcja, $params, $ustawienia);
        }

        return $html;
    }

    /**
     * Jedna sekcja: dobór produktów, próg widoczności i zajawki.
     *
     * @param array<string,mixed> $sekcja
     * @param array<string,mixed> $params
     */
    private function renderujSekcje(string $strona, array $sekcja, array $params, SectionSettings $ustawienia): string
    {
        $ids = $this->dobierz($strona, $sekcja, $params);

        // Próg: sklep woli nie pokazywać sekcji wcale, niż pokazać ją z dwoma
        // produktami w rzędzie na cztery karty.
        if (count($ids) < max(1, (int) $sekcja['threshold'])) {
            return '';
        }

        $produkty = $this->zajawki($ids);

        if ($produkty === []) {
            return '';
        }

        $this->context->smarty->assign([
            'qpsProdukty' => $produkty,
            // Numer sekcji w nazwie: dwie sekcje na tej samej stronie muszą
            // mieć różne id karuzeli, inaczej strzałki drugiej przewijałyby
            // pierwszą.
            'qpsId' => 'listing-section-' . $sekcja['id'],
            'qpsTytul' => $ustawienia->sectionTitle(
                $sekcja,
                (int) $this->context->language->id,
                $this->trans('Recommended', [], self::DOMENA_SKLEPU)
            ),
            'qpsIkonaSvg' => $sekcja['icon'] !== '' ? IconLibrary::svg((string) $sekcja['icon']) : '',
            'qpsIle' => (int) $sekcja['count'],
            'qpsStrzalki' => (bool) $sekcja['arrows'],
        ]);

        return $this->fetch('module:quince_product_section/views/templates/hook/section.tpl');
    }

    /**
     * Numery produktów do pokazania w jednej sekcji.
     *
     * @param array<string,mixed> $sekcja
     * @param array<string,mixed> $params
     *
     * @return int[]
     */
    private function dobierz(string $strona, array $sekcja, array $params): array
    {
        $provider = new SectionProvider();
        $idShop = (int) $this->context->shop->id;
        $ile = (int) $sekcja['count'];
        $pomin = $sekcja['exclude_listing'] ? $this->produktyZListy($params) : [];

        if ($sekcja['algorithm'] === 'manual') {
            $wskazane = array_values(array_filter(array_map('intval', explode(',', (string) $sekcja['ids']))));

            return array_slice($provider->byIds($wskazane, $idShop), 0, $ile);
        }

        if ($sekcja['algorithm'] === 'recently_viewed') {
            return $provider->recentlyViewed($ile, $pomin, $idShop);
        }

        if ($strona === 'CAT') {
            return $provider->fromCategory(
                (int) ($params['idCategory'] ?? 0),
                (string) $sekcja['algorithm'],
                $ile,
                (bool) $sekcja['subcats'],
                $pomin,
                $idShop
            );
        }

        if ($strona === 'MFR') {
            return $provider->fromManufacturer(
                (int) ($params['idManufacturer'] ?? 0),
                (string) $sekcja['algorithm'],
                $ile,
                $pomin,
                $idShop
            );
        }

        return $provider->fromShop((string) $sekcja['algorithm'], $ile, $pomin, $idShop);
    }

    /**
     * Numery produktów stojących już na liście, nad sekcją.
     *
     * @param array<string,mixed> $params
     *
     * @return int[]
     */
    private function produktyZListy(array $params): array
    {
        $lista = $params['listing']['products'] ?? [];

        if (!is_array($lista)) {
            return [];
        }

        $ids = [];

        foreach ($lista as $produkt) {
            $id = (int) ($produkt['id_product'] ?? $produkt['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Produkty w postaci, której oczekuje zajawka motywu — ta sama, co na
     * listingu, żeby karta wyglądała wszędzie tak samo.
     *
     * @param int[] $ids
     *
     * @return array<int,mixed>
     */
    private function zajawki(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $assembler = new ProductAssembler($this->context);
        $ustawieniaPrezentacji = (new ProductPresenterFactory($this->context))->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever($this->context->link),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $surowe = $assembler->assembleProducts(array_map(
            static fn (int $id): array => ['id_product' => $id],
            $ids
        ));

        $wynik = [];

        foreach ($surowe as $surowy) {
            $wynik[] = $presenter->present($ustawieniaPrezentacji, $surowy, $this->context->language);
        }

        return $wynik;
    }
}
