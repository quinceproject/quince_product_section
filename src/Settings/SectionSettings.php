<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\ProductSection\Settings;

use Configuration;
use Quince\Engine\Settings\ConfigStore;

/**
 * Ustawienia sekcji „polecane" pod listą produktów.
 *
 * Siedem stron z listą produktów, każda z własną LISTĄ sekcji: kategoria, marka,
 * wyniki wyszukiwania, bestsellery, nowości, promocje i dostawca. Sklep włącza
 * stronę osobno i dokłada pod nią tyle rzędów, ile chce, każdy z własnym
 * sposobem doboru, licznikiem i nagłówkiem, w ustalonej przez siebie kolejności.
 *
 * Komplet jednej strony idzie do jednego wpisu w ustawieniach jako JSON:
 * `{"enabled": bool, "sections": [...]}`. Rozbicie na osobne klucze dałoby ich
 * dziesiątki na stronę i zmienną liczbę zależną od tego, ile sekcji sklep dodał.
 */
final class SectionSettings
{
    private const PREFIX = 'QUINCE_PSECTION_';

    /**
     * Strony z listą produktów: klucz ustawień => `php_self` kontrolera.
     *
     * Po `php_self` moduł poznaje, na której stronie stoi, bo wszystkie siedem
     * woła ten sam hook z jednego szablonu listingu.
     */
    public const PAGES = [
        'CAT' => 'category',
        'MFR' => 'manufacturer',
        'SEARCH' => 'search',
        'BEST' => 'best-sales',
        'NEW' => 'new-products',
        'PROMO' => 'prices-drop',
        'SUPPL' => 'supplier',
    ];

    /** Nazwy stron na ekranie ustawień, w kolejności wyświetlania. */
    public const PAGE_LABELS = [
        'CAT' => 'Category page',
        'MFR' => 'Brand page',
        'SEARCH' => 'Search results',
        'BEST' => 'Best sellers page',
        'NEW' => 'New products page',
        'PROMO' => 'Promotions page',
        'SUPPL' => 'Supplier page',
    ];

    /**
     * Strony, które mają własny katalog produktów, więc dobór może trzymać się
     * kontekstu. Na pozostałych sekcja sięga po produkty z całego sklepu.
     */
    public const CONTEXT_PAGES = ['CAT', 'MFR'];

    /**
     * Sposoby doboru produktów.
     *
     * „Najczęściej szukane" jest opcjonalne: zależy od `quince_search_score`.
     * Kontroler chowa ten klucz z dropdownu, gdy modułu nie ma; tu zostaje,
     * żeby sekcja zapisana wcześniej dalej się odczytała (gaśnie po cichu,
     * patrz `SectionProvider::pobierz()`).
     */
    public const ALGORITHMS = [
        'bestselling' => 'Best selling',
        'newest' => 'Newest',
        'random' => 'Random',
        'price_asc' => 'Cheapest first',
        'price_desc' => 'Most expensive first',
        'recently_viewed' => 'Recently viewed by the customer',
        'searched' => 'Most searched',
        'manual' => 'Hand-picked products',
    ];

    public const COUNT_MIN = 1;
    public const COUNT_MAX = 24;

    /** Wartości początkowe nowo dodanej sekcji. */
    private const DEFAULTS = [
        'algorithm' => 'bestselling',
        'count' => 4,
        'threshold' => 0,
        'exclude_listing' => true,
        'subcats' => false,
        'title' => [],
        'icon' => '',
        'arrows' => true,
        'ids' => '',
    ];

    /**
     * Ustawienia wszystkich stron.
     *
     * @return array<string,array{enabled:bool,sections:array<int,array<string,mixed>>}>
     */
    public function all(): array
    {
        $out = [];

        foreach (array_keys(self::PAGES) as $strona) {
            $out[$strona] = $this->page($strona);
        }

        return $out;
    }

    /**
     * Ustawienia jednej strony: czy włączona i jej lista sekcji, każda
     * uzupełniona o brakujące pola i przycięta do dozwolonych wartości.
     *
     * @return array{enabled:bool,sections:array<int,array<string,mixed>>}
     */
    public function page(string $strona): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . $strona), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];

        // Format sprzed listy sekcji: jedna sekcja wprost w korzeniu, bez
        // klucza `sections`. Odczyt zamienia go w listę jednoelementową,
        // żeby rząd ustawiony wcześniej nie zgasł sam, przez samą aktualizację
        // modułu, bez żadnej zmiany zrobionej przez sklep.
        if (!array_key_exists('sections', $zapisane) && array_key_exists('algorithm', $zapisane)) {
            $zapisane = [
                'enabled' => $zapisane['enabled'] ?? false,
                'sections' => [$zapisane],
            ];
        }

        $surowe = is_array($zapisane['sections'] ?? null) ? $zapisane['sections'] : [];
        $sekcje = [];

        foreach ($surowe as $sekcja) {
            $sekcje[] = $this->sanitizujSekcje($sekcja, $strona);
        }

        return [
            'enabled' => (bool) ($zapisane['enabled'] ?? false),
            'sections' => $sekcje,
        ];
    }

    /** Strona, na której stoi kontroler o podanym `php_self`, albo nic. */
    public function pageByController(string $phpSelf): ?string
    {
        $klucz = array_search($phpSelf, self::PAGES, true);

        return $klucz === false ? null : (string) $klucz;
    }

    /** Tytuł danej sekcji w podanym języku, a bez tłumaczenia zapasowy. */
    public function sectionTitle(array $sekcja, int $idLang, string $zapasowy): string
    {
        $tytuly = is_array($sekcja['title'] ?? null) ? $sekcja['title'] : [];

        if (isset($tytuly[$idLang]) && trim((string) $tytuly[$idLang]) !== '') {
            return (string) $tytuly[$idLang];
        }

        $domyslny = (int) Configuration::get('PS_LANG_DEFAULT');

        if (isset($tytuly[$domyslny]) && trim((string) $tytuly[$domyslny]) !== '') {
            return (string) $tytuly[$domyslny];
        }

        return $zapasowy;
    }

    /** @return string[] */
    public function configurationKeys(): array
    {
        return array_map(
            static fn (string $strona): string => self::PREFIX . $strona,
            array_keys(self::PAGES)
        );
    }

    /**
     * Wartości początkowe przy instalacji: strona wyłączona, bez sekcji.
     *
     * Zapis idzie też do wiersza globalnego, żeby sklep założony później
     * i panel otwarty na „wszystkich sklepach" dostały to samo.
     */
    public function seed(): void
    {
        foreach (array_keys(self::PAGES) as $strona) {
            $json = (string) json_encode(['enabled' => false, 'sections' => []]);

            ConfigStore::set(self::PREFIX . $strona, $json, true);
            Configuration::updateGlobalValue(self::PREFIX . $strona, $json, true);
        }
    }

    /**
     * Zapis formularza.
     *
     * Pola przychodzą z przedrostkiem strony: prosty przełącznik `CAT_enabled`
     * i jedno pole JSON `CAT_sections` z całą listą sekcji tej strony, spisane
     * przez skrypt ekranu przy wysyłce.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        foreach (array_keys(self::PAGES) as $strona) {
            if (!array_key_exists($strona . '_submitted', $values)) {
                continue;
            }

            $p = $strona . '_';

            $surowe = json_decode((string) ($values[$p . 'sections'] ?? '[]'), true);
            $surowe = is_array($surowe) ? $surowe : [];

            $sekcje = [];

            foreach ($surowe as $sekcja) {
                $sekcje[] = $this->sanitizujSekcje($sekcja, $strona);
            }

            $zapis = [
                'enabled' => ((string) ($values[$p . 'enabled'] ?? '0')) === '1',
                'sections' => $sekcje,
            ];

            ConfigStore::set(self::PREFIX . $strona, (string) json_encode($zapis), true);
        }
    }

    /**
     * Jedna sekcja, uzupełniona i przycięta do dozwolonych wartości.
     *
     * Ta sama funkcja czyta zapisane ustawienia i sanuje dane przychodzące
     * z formularza: przychodzą w tej samej postaci (tablica po `json_decode`).
     *
     * @param mixed $surowa
     *
     * @return array<string,mixed>
     */
    private function sanitizujSekcje($surowa, string $strona): array
    {
        $surowa = is_array($surowa) ? $surowa : [];

        $algorytm = (string) ($surowa['algorithm'] ?? self::DEFAULTS['algorithm']);

        if (!isset(self::ALGORITHMS[$algorytm])) {
            $algorytm = self::DEFAULTS['algorithm'];
        }

        // Tytuł jako `{"1":"Polecane","2":"Recommended"}`, jeden wpis na wszystkie języki.
        $tytul = [];

        foreach ((is_array($surowa['title'] ?? null) ? $surowa['title'] : []) as $idLang => $tekst) {
            $idLang = (int) $idLang;

            if ($idLang > 0) {
                $tytul[$idLang] = (string) $tekst;
            }
        }

        $id = trim((string) ($surowa['id'] ?? ''));

        if ($id === '') {
            $id = 'section-' . bin2hex(random_bytes(4));
        }

        return [
            'id' => $id,
            'active' => (bool) ($surowa['active'] ?? true),
            'algorithm' => $algorytm,
            'count' => $this->ileWZakresie((int) ($surowa['count'] ?? self::DEFAULTS['count'])),
            'threshold' => max(0, (int) ($surowa['threshold'] ?? 0)),
            'exclude_listing' => (bool) ($surowa['exclude_listing'] ?? true),
            'subcats' => $strona === 'CAT' ? (bool) ($surowa['subcats'] ?? false) : false,
            'title' => $tytul,
            'icon' => (string) ($surowa['icon'] ?? ''),
            'arrows' => (bool) ($surowa['arrows'] ?? true),

            // Tylko cyfry i przecinki: pole przyjmuje numery produktów,
            // a wszystko inne i tak nie trafiłoby w żaden produkt.
            'ids' => (string) preg_replace('/[^0-9,]/', '', (string) ($surowa['ids'] ?? '')),
        ];
    }

    private function ileWZakresie(int $ile): int
    {
        return max(self::COUNT_MIN, min(self::COUNT_MAX, $ile));
    }
}
