{**
 * Quince Product Sections — rząd polecanych pod listą produktów.
 *
 * Moduł dobiera produkty, a wygląd bierze z motywu: ta sama karuzela i ta sama
 * zajawka, co akcesoria i produkty do dokupienia.
 *}

{include file='catalog/_partials/product-carousel.tpl'
  qProdukty=$qpsProdukty
  qId=$qpsId
  qTytul=$qpsTytul
  qIkonaSvg=$qpsIkonaSvg
  qIle=$qpsIle
  qStrzalki=$qpsStrzalki
  qKontener=false}
