<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\ProductSection\Products;

use Db;

/**
 * Dobór produktów do sekcji pod listą.
 *
 * Zwraca same numery produktów, a nie gotowe zajawki: w zajawkę zamienia je
 * moduł, tym samym prezenterem co listing, żeby karta wyglądała wszędzie
 * tak samo. Dzięki temu klasa nie zna ani motywu, ani kontekstu sklepu.
 *
 * Dobór zawsze omija produkty już pokazane na liście, jeśli sklep tak ustawił:
 * sekcja „polecane" powtarzająca pierwszy rząd listingu nie poleca niczego.
 */
final class SectionProvider
{
    /**
     * Produkty z kategorii, z jej podkategoriami albo bez.
     *
     * @param int[] $pomin
     *
     * @return int[]
     */
    public function fromCategory(
        int $idCategory,
        string $sposob,
        int $ile,
        bool $zPodkategoriami,
        array $pomin,
        int $idShop
    ): array {
        if ($idCategory <= 0) {
            return [];
        }

        $kategorie = $zPodkategoriami
            ? $this->drzewoKategorii($idCategory)
            : [$idCategory];

        $warunek = 'cp.id_category IN (' . implode(',', array_map('intval', $kategorie)) . ')';

        return $this->pobierz(
            'INNER JOIN ' . _DB_PREFIX_ . 'category_product cp ON cp.id_product = p.id_product',
            $warunek,
            $sposob,
            $ile,
            $pomin,
            $idShop
        );
    }

    /**
     * Produkty jednej marki.
     *
     * @param int[] $pomin
     *
     * @return int[]
     */
    public function fromManufacturer(int $idManufacturer, string $sposob, int $ile, array $pomin, int $idShop): array
    {
        if ($idManufacturer <= 0) {
            return [];
        }

        return $this->pobierz(
            '',
            'p.id_manufacturer = ' . $idManufacturer,
            $sposob,
            $ile,
            $pomin,
            $idShop
        );
    }

    /**
     * Produkty z całego sklepu.
     *
     * @param int[] $pomin
     *
     * @return int[]
     */
    public function fromShop(string $sposob, int $ile, array $pomin, int $idShop): array
    {
        return $this->pobierz('', '1', $sposob, $ile, $pomin, $idShop);
    }

    /**
     * Produkty ostatnio oglądane przez tego klienta.
     *
     * Ciastko `viewed` zakłada moduł `ps_viewedproduct` i trzyma w nim numery
     * produktów od najstarszego. Gdy klient nie obejrzał jeszcze niczego albo
     * modułu nie ma, sekcja nie pokazuje nic: podstawienie bestsellerów
     * nazwałoby „ostatnio oglądanymi" produkty, których klient nie widział.
     *
     * @param int[] $pomin
     *
     * @return int[]
     */
    public function recentlyViewed(int $ile, array $pomin, int $idShop): array
    {
        $ciastko = (string) ($_COOKIE['viewed'] ?? '');

        if ($ciastko === '') {
            return [];
        }

        $ids = array_reverse(array_values(array_filter(array_map('intval', explode(',', $ciastko)))));
        $ids = array_values(array_diff($ids, array_map('intval', $pomin)));

        if ($ids === []) {
            return [];
        }

        return $this->byIds(array_slice($ids, 0, $ile), $idShop);
    }

    /**
     * Wskazane produkty, w kolejności podanej przez sklep.
     *
     * Przepuszczone przez to samo sito co reszta: produkt wyłączony albo
     * niewidoczny w katalogu nie wróci tu tylnymi drzwiami.
     *
     * @param int[] $ids
     *
     * @return int[]
     */
    public function byIds(array $ids, int $idShop): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $lista = implode(',', $ids);

        $sql = 'SELECT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps
                    ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                WHERE p.id_product IN (' . $lista . ')
                  AND ps.active = 1
                  AND ps.visibility IN ("both", "catalog")
                ORDER BY FIELD(p.id_product, ' . $lista . ')';

        return $this->numery($sql);
    }

    /**
     * Wspólne zapytanie dla wszystkich sposobów doboru.
     *
     * @param int[] $pomin
     *
     * @return int[]
     */
    private function pobierz(
        string $zlaczenie,
        string $warunek,
        string $sposob,
        int $ile,
        array $pomin,
        int $idShop
    ): array {
        // „Najczęściej szukane" zależy od `quince_search_score`. Bez modułu
        // sekcja gaśnie po cichu, zamiast wywalić zapytanie na nieistniejącej
        // tabeli: sklep, który go odinstalował, dostaje przypadkowy dobór,
        // nie błąd.
        if ($sposob === 'searched' && !\Module::getInstanceByName('quince_search_score')) {
            $sposob = 'random';
        }

        $sprzedaz = $sposob === 'bestselling'
            ? 'LEFT JOIN ' . _DB_PREFIX_ . 'product_sale psa ON psa.id_product = p.id_product'
            : '';

        $wyszukiwane = $sposob === 'searched'
            ? 'LEFT JOIN ' . _DB_PREFIX_ . 'quince_search_score_products qss ON qss.id_product = p.id_product'
            : '';

        $bez = '';

        if ($pomin !== []) {
            $bez = ' AND p.id_product NOT IN (' . implode(',', array_map('intval', $pomin)) . ')';
        }

        $sql = 'SELECT DISTINCT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps
                    ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                ' . $zlaczenie . '
                ' . $sprzedaz . '
                ' . $wyszukiwane . '
                WHERE ' . $warunek . '
                  AND ps.active = 1
                  AND ps.visibility IN ("both", "catalog")
                  ' . $bez . '
                ORDER BY ' . $this->kolejnosc($sposob) . '
                LIMIT ' . max(1, $ile);

        return $this->numery($sql);
    }

    /**
     * Wszystkie kategorie poniżej wskazanej, razem z nią.
     *
     * Wszerz, a nie rekurencyjnie: drzewo katalogu bywa płytkie i szerokie,
     * a jedno zapytanie na poziom to kilka zapytań zamiast setki.
     *
     * @return int[]
     */
    private function drzewoKategorii(int $idCategory): array
    {
        $wszystkie = [$idCategory];
        $poziom = [$idCategory];

        while ($poziom !== []) {
            $rodzice = implode(',', array_map('intval', $poziom));
            $wiersze = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                'SELECT id_category FROM ' . _DB_PREFIX_ . 'category
                 WHERE id_parent IN (' . $rodzice . ') AND active = 1'
            );

            $poziom = [];

            foreach (is_array($wiersze) ? $wiersze : [] as $wiersz) {
                $id = (int) $wiersz['id_category'];

                // Zabezpieczenie przed zapętleniem: katalog po nieudanym
                // imporcie potrafi mieć kategorię będącą własnym przodkiem.
                if (in_array($id, $wszystkie, true)) {
                    continue;
                }

                $wszystkie[] = $id;
                $poziom[] = $id;
            }
        }

        return $wszystkie;
    }

    private function kolejnosc(string $sposob): string
    {
        switch ($sposob) {
            case 'newest':
                return 'p.date_add DESC';
            case 'bestselling':
                return 'IFNULL(psa.quantity, 0) DESC';
            case 'price_asc':
                return 'ps.price ASC';
            case 'price_desc':
                return 'ps.price DESC';
            case 'searched':
                return 'COALESCE(qss.score, 0) DESC, p.id_product ASC';
            default:
                return 'RAND()';
        }
    }

    /**
     * @return int[]
     */
    private function numery(string $sql): array
    {
        $wiersze = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!is_array($wiersze)) {
            return [];
        }

        return array_map(static fn (array $w): int => (int) $w['id_product'], $wiersze);
    }
}
