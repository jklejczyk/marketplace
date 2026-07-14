# Marketplace multi-vendor — MongoDB w praktyce

Sklep z wieloma sprzedawcami napisany w **Laravel 13 + MongoDB 8**. Projekt do nauki bazy
NoSQL na realistycznym przykładzie: katalog produktów, koszyk, zamówienia, wyszukiwarka.
Skupiłem się na **decyzjach projektowych** — jak ułożyć dane i zapytania w MongoDB — a nie
na wyglądzie aplikacji.

> Projekt edukacyjny. Chodziło o świadome użycie mechanizmów MongoDB z uzasadnieniem każdej decyzji.

## Stack

Laravel 13 · MongoDB 8 · `mongodb/laravel-mongodb` · Atlas Search (wyszukiwarka pełnotekstowa
wbudowana w MongoDB) · Redis · Docker (Laravel Sail) · Pest (testy) · PHPStan/Larastan
(analiza statyczna kodu).

**Zakres:** to jest **backend** — API w formacie JSON (`/api/v1/`) plus testy. Projekt jest
o strukturze danych i zapytaniach, nie o interfejsie. Frontendu sklepu **nie ma** —
Vue/Inertia zostały tylko jako domyślny szablon Laravela (logowanie, dashboard).

Ilość danych wygenerowanych na start: około 10 000 produktów, 100 sprzedawców, 3 000 zamówień,
9 500 recenzji.

## Dlaczego MongoDB, a nie Postgres

MongoDB nie został tu wybrany dlatego, że jest szybszy od Postgresa — przy zwykłym
zapisywaniu i odczytywaniu danych Postgres sprawdziłby się równie dobrze. MongoDB ma
przewagę w trzech konkretnych miejscach:

- **Dane, które zawsze czyta się razem, trzymam w jednym dokumencie — bez łączenia tabel.**
  Warianty produktu, pozycje zamówienia, koszyk. W MongoDB to jeden odczyt. W Postgresie
  byłoby to kilka tabel i łączenie ich zapytaniem (JOIN).
- **Dowolne atrybuty produktu bez zmiany struktury bazy.** Pole `attributes` ma różne klucze
  dla różnych kategorii (`material`, `kraj`, `pojemność`...). W MongoDB to zwykłe pole.
  W Postgresie trzeba by kolumny JSON — działa, ale to już znak, że model tabelaryczny
  nie pasuje.
- **Zapytania analityczne (wyszukiwarka z filtrami, rekomendacje, raporty) robię wprost
  w bazie**, bez dokładania osobnego silnika typu Elasticsearch.

Gdzie Postgres byłby lepszy: sztywne powiązania między tabelami, spójność wymuszana przez
bazę, swobodne raporty pisane w SQL. Tutaj świadomie zrezygnowałem z tych gwarancji na rzecz
prostszych odczytów.

## Jak ułożyłem dane (najważniejsza decyzja)

W MongoDB istnieją dwie możliwości: dane **zagnieżdżone** (w jednym dokumencie razem
z rodzicem) albo **powiązane** (w osobnej kolekcji, połączone przez identyfikator). Wybór między nimi to
sedno projektu. Zasada, którą przyjąłem: **zagnieżdżam, gdy dane czyta się razem i jest ich
mało; wydzielam, gdy mogą rosnąć w nieskończoność.**

| Dane | Sposób | Dlaczego |
|---|---|---|
| Warianty produktu | zagnieżdżone | zawsze czytane z produktem, jest ich kilka |
| Atrybuty produktu | zagnieżdżone, dowolne klucze | różne dla różnych kategorii |
| Recenzje | osobna kolekcja | mogą urosnąć do tysięcy na produkt |
| Pozycje zamówienia | zagnieżdżone, jako kopia | zamówienie to zapis historyczny |
| Koszyk | zagnieżdżony, żywe odwołanie | pokazuje aktualną cenę |
| Sprzedawca | osobna kolekcja | wspólny dla wielu produktów |

**Kopia danych vs żywe odwołanie.** Pozycja zamówienia trzyma **kopię** ceny i nazwy z chwili
zakupu. Dzięki temu późniejsza zmiana ceny produktu **nie psuje historii zamówień** — faktura
sprzed roku pokazuje to, co klient faktycznie zapłacił. Koszyk działa odwrotnie — trzyma tylko
odwołanie do produktu, żeby zawsze pokazać aktualną cenę. Cały proces przebiega tak:
w koszyku widoczna jest aktualna cena, w chwili składania zamówienia cena zostaje zamrożona,
a w samym zamówieniu pozostaje jej kopia na zawsze.

**Dlaczego recenzje osobno, a nie w produkcie.** Rozważałem trzymanie recenzji wewnątrz
produktu. Odrzuciłem, ponieważ popularny produkt mógłby mieć tysiące recenzji, a jeden dokument
w MongoDB ma limit 16 MB. Do tego każde wyświetlenie produktu ciągnęłoby wszystkie recenzje.
Dlatego recenzje są w osobnej kolekcji, a na produkcie trzymam tylko **wyliczoną średnią ocen
i liczbę recenzji** (przeliczane po każdej zmianie recenzji).

**Użytkownik w innej bazie.** Konto użytkownika zostało w SQLite (od logowania). Dlatego
zamówienie i recenzja wskazują na użytkownika zwykłą liczbą, a nie identyfikatorem MongoDB —
bo baza nie potrafi łączyć danych między dwoma różnymi silnikami. Żeby lista zamówień nie
musiała za każdym razem pytać SQLite o dane kupującego, zapisuję jego imię i e-mail razem
z zamówieniem (kopia).

## Zapytania analityczne (aggregation pipeline)

Wyszukiwarkę z filtrami i raporty zrobiłem **potokiem przetwarzania** — dane przechodzą przez
kolejne etapy (filtruj, pogrupuj, policz), trochę jak taśma produkcyjna. To odpowiednik
`GROUP BY` i `JOIN` z SQL, tyle że bardziej rozbudowany. Cztery przykłady:

- **Wyszukiwarka z filtrami.** Jednym zapytaniem zwracam wyniki i jednocześnie liczniki obok
  filtrów ("Skóra (1732)", "Rozmiar M (…)"). Osobno liczę podział po sprzedawcach, po
  przedziałach cenowych, po rozmiarach i po atrybutach.
- **"Kupili też".** Dla danego produktu szukam innych produktów, które kupowano w tych samych
  zamówieniach, i sortuję po liczbie wspólnych zakupów.
- **Najlepsi sprzedawcy wg obrotu.** Sumuję wartość sprzedaży po każdym sprzedawcy. Liczone
  na typie dokładnym dla pieniędzy (Decimal128), nie na liczbie zmiennoprzecinkowej, żeby
  nie gubić groszy.
- **Najczęściej oceniane produkty** ze średnią oceną.

Liczniki po atrybutach są ciekawe technicznie: skoro klucze atrybutów są dowolne, zamieniam
obiekt `{material, kraj}` na listę par `[{klucz, wartość}, ...]` i dopiero wtedy grupuję.
Dzięki temu liczę dowolne atrybuty, nie znając ich nazw z góry.

## Zamówienia i transakcje

Złożenie zamówienia to operacja **niepodzielna** — albo wykona się w całości, albo wcale.
Po kolei: zmniejszam stan magazynowy wariantu, tworzę zamówienie, a jeśli którykolwiek krok
się nie powiedzie (np. zabrakło towaru) — wszystko się cofa.

Kluczowy szczegół przy magazynie: warunek "jest wystarczający stan" i samo zmniejszenie stanu
dzieją się w **jednej operacji na bazie**. Dzięki temu dwóch klientów kupujących ostatnią
sztukę w tej samej chwili nie sprzeda jej podwójnie — baza obsłuży to bezpiecznie.

Transakcje wymagają MongoDB w trybie replica set (zestaw serwerów) — pojedynczy serwer ich
nie obsługuje.

Bezpieczeństwo: kupujący **nigdy** nie jest brany z danych przysłanych przez przeglądarkę,
tylko z zalogowanej sesji. Gdyby był z żądania, klient mógłby podać cudze ID i złożyć
zamówienie w imieniu innej osoby.

## Indeksy (przyspieszanie zapytań)

Indeks działa jak alfabetyczny spis na końcu książki — zamiast przeglądać całą kolekcję,
baza od razu trafia do potrzebnych danych. Dla każdego indeksu sprawdziłem narzędziem
`explain`, że faktycznie jest używany (baza korzysta z indeksu, zamiast przeglądać wszystko
po kolei).

| Indeks | Rodzaj | Do czego |
|---|---|---|
| `listing_idx` | złożony (kilka pól naraz) | lista produktów z filtrem i sortowaniem |
| `tags_idx` | po tablicy | filtr po tagach |
| `in_stock_idx` | częściowy (tylko dostępne produkty) | mniejszy, bo pomija niedostępne |
| `attributes_wildcard_idx` | uniwersalny (dowolny atrybut) | filtr po dowolnym atrybucie |
| `order_items_product_idx` | po tablicy pozycji | "kupili też" |
| `review_product_idx` | po produkcie | średnia ocen, najczęściej oceniane |
| `location_2dsphere` | geograficzny | wyszukiwanie po odległości |
| `cart_ttl_idx` | czasowy | porzucone koszyki znikają po 7 dniach |

Przykładowy efekt: przy liście produktów baza zamiast czytać 10 000 dokumentów sprawdza 474.

Indeks uniwersalny (`wildcard`) działa tak samo, jak w Postgresie indeks na kolumnie JSON —
pozwala szukać po polach, których nazwy nie są znane z góry.

## Wyszukiwarka pełnotekstowa (Atlas Search)

MongoDB w tym obrazie ma wbudowaną wyszukiwarkę opartą na Lucene (tym samym silniku, co
Elasticsearch). Sama nadąża za zmianami w bazie, więc nie trzeba dokładać osobnej wyszukiwarki
ani jej synchronizować.

- **Działa polski stemmer** — czyli wyszukiwarka rozumie odmiany słów ("buty" znajdzie
  "butów"). Natywny mechanizm MongoDB tego nie potrafił, Atlas Search tak.
- **Tolerancja literówek.** To samo pole indeksuję na dwa sposoby: raz z odmianą polską, raz
  bez — bo mechanizm od odmian psuje wykrywanie literówek na końcu słowa.
- Trafienia w nazwę produktu liczą się mocniej niż w opis (ustawiane wagi).

Elasticsearch nadal ma sens w dwóch sytuacjach: przy przeszukiwaniu wielu różnych źródeł
naraz oraz przy bardzo zaawansowanym strojeniu wyszukiwania. Dla tego projektu Atlas Search
w zupełności wystarcza.

## Wyszukiwanie geograficzne

- **Wyszukiwanie po odległości** zwraca sprzedawców w zadanym promieniu (na przykład 5 km),
  posortowanych od najbliższego, wraz z odległością do każdego.
- **Wyszukiwanie po obszarze** sprawdza, czy sprzedawca znajduje się w wyznaczonej strefie
  dostawy, czyli w dowolnym wielokącie na mapie.

## Sprawdzanie poprawności danych w bazie

MongoDB domyślnie przyjmie każdy dokument. Dodałem regułę poprawności na poziomie samej bazy
(nie tylko aplikacji) dla zamówień i produktów. Baza odrzuca błędny dokument nawet przy
ręcznym zapisie z konsoli.

- Zamówienia: status musi być jednym z ustalonych ("nowe", "opłacone", "wysłane"...), muszą
  być obecne wymagane pola i właściwe typy.
- Produkty: nazwa 3–200 znaków, cena większa od zera, przynajmniej jeden wariant.

**Ta reguła wyłapała prawdziwe błędy.** Po jej włączeniu okazało się, że jedna ścieżka
tworzenia zamówienia zapisywała je bez statusu, a część testów tworzyła niekompletne dane,
które baza wcześniej po cichu przyjmowała. Sprawdzanie tylko w aplikacji (w jednym miejscu)
nie wychwyciłoby innych ścieżek zapisu — reguła w bazie działa niezależnie od tego, kto zapisuje.

## API

Zastosowany wzorzec: cienki kontroler przekazuje żądanie do klasy z logiką, a wynik wraca
przez klasę formatującą odpowiedź. Adresy są wersjonowane pod `/api/v1/`.

| Metoda | Adres | Co robi |
|---|---|---|
| GET | `/api/v1/products` | wyszukiwarka z filtrami i licznikami |
| GET | `/api/v1/products/search?q=` | wyszukiwarka pełnotekstowa |
| GET | `/api/v1/products/most-reviewed` | najczęściej oceniane |
| GET | `/api/v1/products/{product}/frequently-bought-together` | "kupili też" |
| POST | `/api/v1/orders` | złożenie zamówienia (wymaga logowania) |
| GET | `/api/v1/vendors/top` | najlepsi sprzedawcy wg obrotu |
| GET | `/api/v1/vendors/nearby?lng=&lat=&radius=` | sprzedawcy w promieniu |
| POST | `/api/v1/vendors/in-area` | sprzedawcy w obszarze |

Drobiazg wart uwagi: MongoDB zwraca własne typy (identyfikator, liczba dokładna), które
w gołym JSON-ie wyglądają dziwnie. Klasa formatująca zamienia je na zwykłe teksty.

## Testy

Testy w Pest, 98 (94 przechodzą, 4 pominięte — wyłączone funkcje logowania). Każdy test
działa na osobnej bazie testowej i sam sprząta po sobie. Testy indeksów sprawdzają nie tylko
wynik, ale i to, że baza faktycznie użyła indeksu.

## Czego świadomie nie zrobiłem

- **Powiadomienia na żywo o statusie zamówienia** — dużo dodatkowej infrastruktury (osobny
  proces, WebSocket), mało wspólnego z samą bazą. Odłożone.
- **Trzymanie zdjęć w bazie (GridFS)** — w prawdziwym sklepie zdjęcia idą do zewnętrznego
  magazynu plików / CDN, nie do bazy. Wartość praktyczna znikoma, więc pominięte.
- **Test wydajności na milionie rekordów** — sprawdzałem wydajność narzędziem `explain` na
  ~10 000 rekordów, nie pełnym testem obciążeniowym.

## Co zrobiłbym inaczej

- **Reguły poprawności w bazie od początku, a nie na końcu.** Dodane późno wyłapały błędy,
  które przy wcześniejszym włączeniu w ogóle by nie powstały.
- **Przeliczanie średniej ocen w tle (kolejka), nie od razu.** Teraz dzieje się w trakcie
  zapisu recenzji — przy dużym ruchu spowalniałoby żądanie.
- **Użytkownik w tej samej bazie co reszta.** Trzymanie go w SQLite wymusiło obejścia.

## Uruchomienie

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed --class=MarketplaceSeeder
./vendor/bin/sail artisan search:index-products   # buduje indeks wyszukiwarki
./vendor/bin/sail artisan test
```

MongoDB musi działać w trybie replica set (potrzebny do transakcji) — w projekcie użyty obraz
`mongodb-atlas-local`.
