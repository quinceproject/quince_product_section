<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\ProductSection\Controller\Admin;

use Module;
use Quince\Engine\Controller\Admin\QuinceController;
use Quince\Engine\Ui\IconLibrary;
use Quince\ProductSection\Settings\SectionSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia rzędów polecanych produktów pod listą.
 *
 * Dziedziczy po klasie z silnika, więc strona dostaje ten sam sidebar, pasek
 * zapisu i wygląd co reszta panelu Quince.
 */
class SettingsController extends QuinceController
{
    private const DOMENA = 'Modules.Quinceproductsection.Admin';

    public function __construct(private readonly SectionSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        $strony = $this->settings->all();

        return $this->renderPanel(
            '@Modules/quince_product_section/views/templates/admin/settings.html.twig',
            [
                'pages' => $this->withIcons($strony),
                'pageLabels' => SectionSettings::PAGE_LABELS,
                'contextPages' => SectionSettings::CONTEXT_PAGES,
                'algorithms' => $this->algorithms(),
                'iconsRoute' => 'quince_icons_search',
                'languages' => $this->languages(),
                'limits' => [
                    'countMin' => SectionSettings::COUNT_MIN,
                    'countMax' => SectionSettings::COUNT_MAX,
                ],
                'layoutTitle' => 'Product sections',
                'layoutTitleDomain' => self::DOMENA,
                'quinceRoute' => 'quince_product_section_settings',
                'quinceNavRoute' => 'quince_product_section_settings',
            ]
        );
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_product_section_settings_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_product_section_settings');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', self::DOMENA));

        return $this->redirectToRoute('quince_product_section_settings');
    }

    /**
     * Sposoby doboru dostępne w dropdownie.
     *
     * „Najczęściej szukane" znika z listy, gdy `quince_search_score` nie jest
     * zainstalowany — wybranie trybu, który i tak nic by nie pokazał, tylko
     * myli sprzedawcę. Sekcja zapisana wcześniej, zanim moduł odinstalowano,
     * dalej działa (gaśnie po cichu — patrz `SectionProvider::pobierz()`),
     * po prostu nie da się jej wybrać od nowa z tego ekranu.
     *
     * @return array<string,string>
     */
    private function algorithms(): array
    {
        $sposoby = SectionSettings::ALGORITHMS;

        if (!Module::getInstanceByName('quince_search_score')) {
            unset($sposoby['searched']);
        }

        return $sposoby;
    }

    /**
     * Sekcje z kodem SVG zapisanej ikony.
     *
     * Wybierak pokazuje ikonę od razu po otwarciu ekranu, a nie dopiero po
     * dopytaniu serwera — inaczej pole wyglądałoby na puste przy każdym wejściu.
     *
     * @param array<string,array{enabled:bool,sections:array<int,array<string,mixed>>}> $strony
     *
     * @return array<string,array{enabled:bool,sections:array<int,array<string,mixed>>}>
     */
    private function withIcons(array $strony): array
    {
        foreach ($strony as $klucz => $ustawienia) {
            foreach ($ustawienia['sections'] as $i => $sekcja) {
                $nazwa = (string) ($sekcja['icon'] ?? '');
                $strony[$klucz]['sections'][$i]['icon_svg'] = $nazwa !== '' ? IconLibrary::svg($nazwa) : '';
            }
        }

        return $strony;
    }
}
