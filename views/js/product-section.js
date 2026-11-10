/**
 * Ekran „Sekcje produktowe" — to, czego nie obsługuje sam znacznik.
 *
 * Siedem osobnych list, jedna na stronę z produktami. Każda działa tak samo
 * jak sekcje strony głównej (ten sam wzorzec, bez zagnieżdżonych kolumn, bo
 * tu wiersz ma jeden, płaski komplet pól):
 *
 *   1. rozwija i zwija wiersz sekcji,
 *   2. dokłada sekcję z gotowego wzoru w `<template>` i kasuje pozycje,
 *   3. pokazuje pole „numery produktów" tylko przy ręcznym doborze,
 *   4. chowa i pokazuje ciało karty razem z jej przełącznikiem,
 *   5. spisuje listę każdej strony do jej własnego pola `{strona}_sections`
 *      przy wysyłce.
 */
(function () {
    'use strict';

    var formularz = document.getElementById('quince-product-section');

    if (!formularz) {
        return;
    }

    var jezyki = JSON.parse(formularz.dataset.languages || '[]');
    var napisy = JSON.parse(formularz.dataset.napisy || '{}');

    // ── drobiazgi ────────────────────────────────────────────────────────

    function pole(gdzie, nazwa) {
        return gdzie.querySelector('[data-pole="' + nazwa + '"]');
    }

    function checkbox(pojemnik) {
        return pojemnik ? pojemnik.querySelector('input[type="checkbox"]') : null;
    }

    function wybranyNapis(select) {
        return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex].text.trim() : '';
    }

    /** Napisy z komponentu wielojęzycznego, sparowane po kolejności języków. */
    function napisyPola(pojemnik) {
        var wynik = {};

        if (!pojemnik) {
            return wynik;
        }

        pojemnik.querySelectorAll('.qc-multilang-row input[type="text"]').forEach(function (wejscie, i) {
            var tekst = wejscie.value.trim();

            if (tekst !== '' && jezyki[i] !== undefined) {
                wynik[jezyki[i]] = tekst;
            }
        });

        return wynik;
    }

    // ── jedna sekcja ─────────────────────────────────────────────────────

    function rozwin(sekcja) {
        var body = sekcja.querySelector(':scope > .qc-rep-body');

        body.hidden = !body.hidden;
        sekcja.classList.toggle('qc-rep-row--otwarty', !body.hidden);
    }

    function odswiezSekcje(sekcja) {
        if (!sekcja) {
            return;
        }

        var algorytm = pole(sekcja, 'algorithm').value;

        sekcja.querySelectorAll('[data-gdy-algorytm]').forEach(function (blok) {
            blok.hidden = blok.dataset.gdyAlgorytm !== algorytm;
        });

        var sztuk = parseInt(pole(sekcja, 'count').value, 10) || 1;

        sekcja.querySelector('[data-opis]').textContent =
            wybranyNapis(pole(sekcja, 'algorithm')).toLowerCase() + ' · ' + sztuk + ' ' + (napisy.products || 'products');
    }

    function odswiezListe(lista) {
        var wiersze = lista.querySelectorAll(':scope > [data-sekcja]');

        wiersze.forEach(function (sekcja, i) {
            var nazwa = sekcja.querySelector('[data-nazwa]');

            nazwa.textContent = nazwa.textContent.replace(/\s*\d+\s*$/, '') + ' ' + (i + 1);

            odswiezSekcje(sekcja);
        });

        var pusto = lista.closest('[data-strona]').querySelector('[data-pusto]');

        if (pusto) {
            pusto.hidden = wiersze.length > 0;
        }
    }

    function odswiezWszystko() {
        formularz.querySelectorAll('[data-sekcje]').forEach(odswiezListe);
    }

    function dodajSekcje(karta) {
        var lista = karta.querySelector('[data-sekcje]');
        var wzor = karta.querySelector('[data-wzor-sekcji]');
        var nowa = wzor.content.firstElementChild.cloneNode(true);

        // Nazwa własna sekcji jest identyfikatorem: silnik rozpoznaje po niej
        // sekcję, więc musi być niepowtarzalna w obrębie tej listy.
        var zajete = [];

        lista.querySelectorAll('[data-sekcja] [data-pole="id"]').forEach(function (p) { zajete.push(p.value); });

        var numer = zajete.length + 1;

        while (zajete.indexOf('section-' + numer) !== -1) {
            numer += 1;
        }

        pole(nowa, 'id').value = 'section-' + numer;
        nowa.setAttribute('x-sort:item', "'section-" + numer + "'");

        lista.appendChild(nowa);
        rozwin(nowa);
        odswiezListe(lista);
    }

    // ── kliknięcia ───────────────────────────────────────────────────────

    formularz.addEventListener('click', function (zdarzenie) {
        var cel = zdarzenie.target.closest('button');
        var sekcja = zdarzenie.target.closest('[data-sekcja]');

        // Klik w pasek rozwija wiersz. Przełącznik i chwyt mają własne zadania.
        if (!cel && zdarzenie.target.closest('[data-pasek]') && !zdarzenie.target.closest('.qc-toggle, .qc-rep-handle')) {
            rozwin(sekcja);

            return;
        }

        if (!cel) {
            return;
        }

        if (cel.hasAttribute('data-rozwin')) {
            rozwin(sekcja);

            return;
        }

        if (cel.hasAttribute('data-dodaj-sekcje')) {
            dodajSekcje(cel.closest('[data-strona]'));

            return;
        }

        if (cel.hasAttribute('data-usun-sekcje')) {
            var lista = sekcja.closest('[data-sekcje]');

            sekcja.remove();
            odswiezListe(lista);
        }
    });

    // Odznaczenie/zaznaczenie strony chowa i pokazuje jej kartę.
    formularz.addEventListener('change', function (zdarzenie) {
        var przelacznik = zdarzenie.target.closest('.qc-card-head .qc-toggle input[type="checkbox"]');

        if (przelacznik) {
            przelacznik.closest('[data-strona]').querySelector('[data-karta-cialo]').hidden = !przelacznik.checked;

            return;
        }

        var sekcja = zdarzenie.target.closest('[data-sekcja]');

        if (sekcja) {
            odswiezSekcje(sekcja);
        }
    });

    formularz.addEventListener('input', function (zdarzenie) {
        odswiezSekcje(zdarzenie.target.closest('[data-sekcja]'));
    });

    // Przeciągnięcie zmienia kolejność w drzewie strony, a nie kliknięciem,
    // więc numery sekcji odświeża obserwator każdej listy.
    formularz.querySelectorAll('[data-sekcje]').forEach(function (lista) {
        new MutationObserver(function () { odswiezListe(lista); }).observe(lista, { childList: true });
    });

    // ── spis do pól formularza ──────────────────────────────────────────

    formularz.addEventListener('submit', function () {
        formularz.querySelectorAll('[data-strona]').forEach(function (karta) {
            var sekcje = [];

            karta.querySelectorAll('[data-sekcje] > [data-sekcja]').forEach(function (sekcja) {
                sekcje.push({
                    id: pole(sekcja, 'id').value,
                    active: checkbox(pole(sekcja, 'active')).checked,
                    algorithm: pole(sekcja, 'algorithm').value,
                    count: parseInt(pole(sekcja, 'count').value, 10) || 1,
                    threshold: parseInt(pole(sekcja, 'threshold').value, 10) || 0,
                    exclude_listing: checkbox(pole(sekcja, 'exclude_listing')).checked,
                    subcats: pole(sekcja, 'subcats') ? checkbox(pole(sekcja, 'subcats')).checked : false,
                    title: napisyPola(pole(sekcja, 'title')),
                    icon: pole(sekcja, 'icon').querySelector('input[type="hidden"]').value,
                    arrows: checkbox(pole(sekcja, 'arrows')).checked,
                    ids: pole(sekcja, 'ids') ? pole(sekcja, 'ids').value.trim() : ''
                });
            });

            karta.querySelector('[data-pole-sekcje]').value = JSON.stringify(sekcje);
        });
    });

    odswiezWszystko();
})();
