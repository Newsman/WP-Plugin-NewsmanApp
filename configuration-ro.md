# Plugin Newsman pentru WordPress - Ghid de Configurare

Acest ghid prezinta toate setarile din plugin-ul Newsman, pentru a va putea conecta magazinul WordPress sau WooCommerce la contul Newsman si a incepe sa colectati abonati, sa trimiteti newslettere si sa urmariti comportamentul clientilor.

## Cuprins

- [Unde Gasiti Setarile Plugin-ului](#unde-gasiti-setarile-plugin-ului)
- [Primii Pasi - Conectarea la Newsman](#primii-pasi---conectarea-la-newsman)
- [Reconfigurare cu Newsman OAuth](#reconfigurare-cu-newsman-oauth)
- [Pagina Settings](#pagina-settings)
- [Pagina Sync](#pagina-sync)
- [Pagina Remarketing](#pagina-remarketing)
- [Pagina SMS (doar WooCommerce)](#pagina-sms-doar-woocommerce)
- [Formulare Elementor (Elementor Pro)](#formulare-elementor-elementor-pro)
- [Atomic Forms (Elementor 4.x, experimental)](#atomic-forms-elementor-4x-experimental)
- [Contact Form 7](#contact-form-7)
- [Intrebari Frecvente](#intrebari-frecvente)

---

## Unde Gasiti Setarile Plugin-ului

Dupa instalarea si activarea plugin-ului, cautati elementul de meniu **NewsMAN** in bara laterala din stanga a panoului de administrare WordPress. Facand click pe el, apar mai multe sub-pagini:

- **NewsMAN** - Pagina principala
- **Sync** - Alegeti ce lista Newsman primeste abonatii
- **Remarketing** - Urmariti comportamentul vizitatorilor din magazin pentru campanii personalizate
- **SMS** - Trimiteti mesaje text clientilor cand se schimba statusul comenzii (disponibil doar daca folositi WooCommerce)
- **Settings** - Conexiunea API, optiuni checkout si setari avansate
- **OAuth** - Configurare rapida prin conectarea directa la contul Newsman

---

## Primii Pasi - Conectarea la Newsman

Inainte de a putea folosi orice functionalitate, trebuie sa conectati plugin-ul la contul dvs. Newsman. Exista doua modalitati:

### Optiunea A: Configurare Rapida cu OAuth (Recomandat)

1. Accesati **NewsMAN > OAuth** in panoul de administrare WordPress.
2. Faceti click pe **Connect with Newsman**.
3. Veti fi redirectionat catre site-ul Newsman. Autentificati-va daca este necesar si acordati acces:

![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/clienti.png)

![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/comenzi_integrari.png)
4. Veti fi redirectionat inapoi catre o pagina de administrare Newsman in WordPress, unde alegeti lista de email dintr-un dropdown. Selectati lista pe care doriti sa o folositi si faceti click pe **Save**.
5. Asta e tot - API Key, User ID si Lista sunt toate configurate.

### Optiunea B: Configurare Manuala

1. Autentificati-va in contul Newsman pe newsman.app.
2. Accesati setarile contului si copiati **API Key** si **User ID**.
3. In WordPress, accesati **NewsMAN > Settings**.
4. Inserati **API Key** si **User ID** in campurile corespunzatoare.
5. Faceti click pe **Save**. Un indicator verde va confirma ca conexiunea a fost realizata cu succes.
6. Acum accesati **NewsMAN > Sync**. Deoarece ati introdus un API Key si User ID valide, dropdown-ul **Select a list** va afisa acum toate listele din contul dvs. Newsman. Alegeti lista pe care doriti sa o folositi si faceti click pe **Save** din nou.

---

## Reconfigurare cu Newsman OAuth

Daca trebuie sa reconectati plugin-ul la un alt cont Newsman, sau daca credentialele s-au schimbat, accesati **NewsMAN > Settings** si faceti click pe butonul **Reconfigure with Newsman OAuth**. Acest lucru va va ghida prin acelasi flux OAuth descris mai sus - veti fi redirectionat catre site-ul Newsman pentru a autoriza accesul, apoi inapoi in WordPress pentru a selecta lista de email. API Key, User ID si Lista vor fi actualizate cu noile credentiale.

---

## Pagina Settings

Accesati **NewsMAN > Settings** pentru a configura comportamentul plugin-ului.

### Setari Generale
![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/setari.png)

- **Allow API access** - Activati aceasta optiune daca doriti ca Newsman sa poata prelua date (precum produse sau abonati) direct din magazinul dvs. Aceasta este necesara pentru functionalitati precum feed-urile de produse in newslettere. Lasati-o dezactivata daca nu aveti nevoie de ea.

![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/cheie.png)

- **Send User IP Address** - Cand un vizitator se aboneaza, plugin-ul poate trimite adresa IP a acestuia catre Newsman. Acest lucru poate ajuta la analiza si conformitate. Daca dezactivati aceasta optiune, plugin-ul va folosi in schimb **Server IP Address** pe care il introduceti mai jos.

- **Server IP Address** - O adresa IP de rezerva folosita cand "Send User IP Address" este dezactivata. De obicei puteti lasa acest camp gol.

- **Import Authorize Header Name / Key** - Aceasta este o optiune veche (legacy) pentru protejarea feed-ului de produse cu credentiale de securitate personalizate. Daca v-ati conectat prin OAuth, nu trebuie sa le setati - plugin-ul gestioneaza autentificarea automat. Trebuie sa le completati doar daca ati configurat conexiunea manual si doriti sa adaugati un nivel suplimentar de securitate la importurile de feed-uri de produse.

### Abonare la Newsletter

![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/inscriere.png)

- **Newsletter Opt-in type** - Alegeti cum sunt adaugati noii abonati:
  - **Opt-in** - Abonatul este adaugat imediat.
  - **Double Opt-in** - Abonatul primeste mai intai un email de confirmare si trebuie sa faca click pe un link pentru a confirma. Aceasta optiune este recomandata pentru conformitatea GDPR.

- **Confirmation email Form ID** - Daca ati ales Double Opt-in mai sus, introduceti Form ID-ul din contul dvs. Newsman. Acesta spune Newsman ce sablon de email de confirmare sa trimita. Puteti gasi acest ID in dashboard-ul Newsman la sectiunea Forms.

### Optiuni Checkout (doar WooCommerce)

Aceste setari adauga un checkbox de newsletter pe pagina de checkout a magazinului, astfel incat clientii se pot abona in timp ce plaseaza o comanda.

![image](https://raw.githubusercontent.com/Newsman/WP-Plugin-NewsmanApp/refs/heads/master/assets/finalizare.png)

- **Enable Newsletter Checkbox** - Activati pentru a afisa un checkbox "Aboneaza-te la newsletter" pe pagina de checkout.

- **Newsletter Checkbox Label** - Personalizati textul afisat langa checkbox. De exemplu: "Da, doresc sa primesc oferte speciale si noutati prin email."

- **Checkbox checked by default** - Daca este activat, checkbox-ul va fi pre-bifat. Clientii vor trebui sa il debifeze daca nu doresc sa se aboneze. Nota: in unele regiuni, pre-bifarea poate sa nu fie conforma cu reglementarile de confidentialitate.

- **Enable SMS sync** - Cand un client plaseaza o comanda, numarul sau de telefon va fi sincronizat cu lista SMS Newsman. Aceasta va permite sa le trimiteti mesaje text ulterior.

- **Enable Order Status Checkbox** - Afiseaza un checkbox suplimentar care intreaba clientii daca doresc sa primeasca actualizari SMS despre statusul comenzii (de ex., "Comanda dvs. a fost expediata").

- **Order Status Checkbox Label** - Personalizati textul pentru checkbox-ul de status al comenzii.

- **Order Status checkbox checked by default** - Daca checkbox-ul de status al comenzii este pre-bifat.

### Newsletter in Contul Meu (doar WooCommerce)

Aceste setari adauga o pagina dedicata de newsletter in zona "Contul Meu" a clientului, unde isi pot gestiona abonarea.

- **Enable** - Activati pentru a adauga pagina de newsletter in Contul Meu.

- **Page Menu Label** - Textul afisat in meniul lateral al Contului Meu (de ex., "Newsletter").

- **Page Title** - Titlul afisat pe pagina de newsletter (de ex., "Abonare Newsletter").

- **Checkbox Label** - Textul de langa checkbox-ul de abonare/dezabonare (de ex., "Doresc sa primesc newslettere si promotii").

### Setari pentru Dezvoltatori

Aceste setari sunt destinate utilizatorilor avansati si dezvoltatorilor. In cele mai multe cazuri, ar trebui sa le lasati la valorile implicite.

- **Logging level** - Controleaza cat de mult detaliu scrie plugin-ul in fisierul de log. Valoarea implicita este **Error**, care inregistreaza doar problemele. Setati la **Debug** daca investigati o problema (dar nu uitati sa il setati inapoi dupa aceea, deoarece modul Debug creeaza fisiere de log mari). Setati la **No Logging** pentru a dezactiva complet logarea.

- **API Timeout** - Cate secunde asteapta plugin-ul un raspuns de la Newsman inainte de a renunta. Valoarea implicita de 10 secunde functioneaza bine pentru majoritatea configuratiilor. Mariti-o doar daca intampinati erori de timeout pe un server lent.

- **Enable Test User IP / Test User IP address** - Doar pentru dezvoltare si testare. Va permite sa simulati o adresa IP specifica de vizitator. Lasati-le dezactivate in productie.

- **Use Elementor Integration** - Comutator principal pentru integrarea Newsman cu Elementor Pro. **Activat implicit.** Cand este activat, sectiunea Newsman apare in editorul Elementor atat pentru widget-ul Form clasic, cat si pentru Atomic Forms (Elementor 4.x), iar trimiterile sunt impinse catre lista Newsman. Dezactivati pentru a elimina toate controalele Newsman din editor si pentru a opri redirectionarea trimiterilor pentru ambele. Setarile Newsman per formular / per camp existente sunt pastrate cand este dezactivat si reactivate cand il porniti din nou. Dezactivarea nu are efect asupra fluxurilor de newsletter WordPress, WooCommerce sau non-Elementor.

- **Use Contact Form 7 Integration** - Comutator principal pentru integrarea Newsman cu Contact Form 7. **Activat implicit.** Vizibil doar cand plugin-ul Contact Form 7 este instalat. Cand este activat, un tab Newsman apare in ecranul de editare al fiecarui formular de contact (selectie lista per formular, camp de email, proprietati), iar trimiterile sunt impinse catre lista Newsman. Dezactivati pentru a elimina tab-ul din editor si a opri redirectionarea trimiterilor. Setarile Newsman per formular existente sunt pastrate cand este dezactivat si reactivate cand il porniti din nou. Dezactivarea nu are efect asupra fluxurilor de newsletter WordPress, WooCommerce, Elementor sau alte integrari.

- **Plugin Loaded Priority** - Controleaza cand se initializeaza plugin-ul in raport cu alte plugin-uri. Schimbati doar daca intampinati conflicte cu alt plugin. Valoarea implicita de 20 functioneaza pentru majoritatea configuratiilor.

- **Use Action Scheduler** - Daca aveti plugin-ul Action Scheduler instalat, activarea acestuia va procesa abonarile si dezabonarile in fundal in loc de imediat. Aceasta poate imbunatati viteza checkout-ului pe magazinele cu trafic mare.

- **Use Action Scheduler for Subscribe / Unsubscribe** - Control detaliat asupra operatiunilor care folosesc procesarea in fundal.

---

## Pagina Sync

Accesati **NewsMAN > Sync** pentru a alege unde sunt trimisi abonatii dvs. in Newsman.

- **Select a list** - Alegeti lista de email Newsman care va primi abonatii dvs. Toate listele din contul dvs. Newsman sunt afisate aici.

- **Select a segment** - Optional, alegeti un segment din lista selectata. Segmentele va permit sa organizati abonatii in grupuri (de ex., "Clienti VIP", "Cititori Blog"). Daca nu folositi segmente, lasati acest camp gol.

- **Select an SMS list** - Alegeti lista SMS Newsman pentru sincronizarea numerelor de telefon.

---

## Pagina Remarketing

Accesati **NewsMAN > Remarketing** pentru a configura urmarirea vizitatorilor. Remarketing-ul permite Newsman sa urmareasca ce pagini si produse vizualizeaza vizitatorii dvs., astfel incat sa le puteti trimite emailuri personalizate (de ex., reamintiri de cos abandonat, recomandari de produse).

- **Use Remarketing** - Activati aceasta optiune pentru a activa pixelul de remarketing pe magazinul dvs.

- **Remarketing ID** - Acesta este completat automat. Identifica magazinul dvs. in sistemul de urmarire Newsman. Nu trebuie sa il modificati.

- **Anonymize IP** - Cand este activat, adresele IP ale vizitatorilor sunt anonimizate inainte de a fi trimise catre Newsman. Recomandat pentru conformitatea GDPR.

- **Send Telephone** - Include numerele de telefon ale clientilor in datele de remarketing. Se aplica doar clientilor autentificati care au furnizat un numar de telefon.

- **Theme Cart Compatibility** - Activati pentru cea mai sigura detectare a schimbarilor din cos pe orice tema (polling in fundal plus interceptare AJAX/fetch catre endpoint-ul de cos Newsman). Dezactivati pentru un mecanism mai usor care citeste datele cosului direct din raspunsurile WooCommerce Store API (`/wc/store/v1/cart/add-item` si `/wc/store/v1/batch`), fara polling. Daca dezactivati aceasta optiune, folositi instrumentul **Check installation** din Remarketing in newsman.app pentru a verifica daca evenimentele de cos sunt detectate pe tema dvs.

- **Product Attributes** - Selectati ce atribute ale produselor (de ex., Culoare, Marime, Brand) sunt trimise impreuna cu evenimentele de vizualizare a produselor. Aceasta va permite sa construiti campanii mai bine directionate in Newsman.

- **Customer Attributes** - Selectati ce detalii ale clientilor sunt trimise cu datele de remarketing. Optiunile disponibile includ companie facturare/livrare, oras, judet si tara.

- **Export WordPress Subscribers** - **OPTIONAL.** Cand este bifat, permite Newsman sa preia utilizatorii WordPress cu rolul "Subscriber" din magazinul dvs.

- **Export WooCommerce Subscribers** - **OPTIONAL.** Cand este bifat, permite Newsman sa preia adresele de email din comenzile WooCommerce cu statusul "Completed" din magazinul dvs.

**Note importante despre Export WordPress Subscribers si Export WooCommerce Subscribers:**

- Daca doriti sa folositi aceasta functionalitate, **bifati doar una dintre ele, nu pe amandoua.** Avand ambele optiuni bifate in acelasi timp nu functioneaza.
- Aceste optiuni permit doar Newsman sa acceseze datele. Importul propriu-zis se configureaza in contul dvs. Newsman: accesati **Newsman.app > Integrations > Plugins > WordPress / WooCommerce plugin > Subscribers**.
- In acea configurare Newsman, ar trebui sa setati o **data de inceput** de la care doriti sa fie preluati abonatii. Daca nu setati o data de inceput, Newsman poate importa toti abonatii de la inceput, ceea ce poate sa nu fie ceea ce doriti.

- **Export Orders on Status Change** - Un dropdown cu selectie multipla unde alegeti ce statusuri de comanda declanseaza trimiterea datelor comenzii catre Newsman. De exemplu, daca selectati "Completed" si "Processing", detaliile comenzii vor fi trimise catre Newsman de fiecare data cand o comanda ajunge la unul dintre aceste statusuri. Aceasta activeaza urmarirea veniturilor si campaniile bazate pe achizitii.

---

## Pagina SMS (doar WooCommerce)

Accesati **NewsMAN > SMS** pentru a configura mesajele text automate trimise clientilor cand se schimba statusul comenzii lor.

### Activare SMS

- **Use SMS** - Activati aceasta optiune pentru a activa notificarile SMS.

- **Select SMS List** - Alegeti ce lista SMS Newsman sa fie folosita pentru trimiterea mesajelor.

### Mesaje SMS pe Status Comanda

Pentru fiecare status de comanda, puteti activa un mesaj text si personaliza continutul acestuia:

| Status Comanda | Cand se trimite |
|---------------|-----------------|
| **Pending** | Comanda primita dar inca neplatita |
| **Failed** | Plata a esuat |
| **On Hold** | In asteptarea confirmarii platii (de ex., transfer bancar) |
| **Processing** | Plata primita, comanda se pregateste |
| **Completed** | Comanda a fost finalizata si expediata |
| **Refunded** | Comanda a fost rambursata |
| **Cancelled** | Comanda a fost anulata |

Pentru fiecare status, bifati caseta **Enable** si scrieti mesajul in zona de text.

### Personalizarea Mesajelor SMS

Puteti folosi variabile in mesaje care vor fi inlocuite cu datele reale ale comenzii. Puneti fiecare variabila intre acolade duble:

| Variabila | Ce devine |
|-----------|-----------|
| `{{billing_first_name}}` | Prenumele clientului (facturare) |
| `{{billing_last_name}}` | Numele de familie al clientului (facturare) |
| `{{shipping_first_name}}` | Prenumele clientului (livrare) |
| `{{shipping_last_name}}` | Numele de familie al clientului (livrare) |
| `{{order_number}}` | Numarul comenzii |
| `{{order_date}}` | Data la care a fost plasata comanda |
| `{{order_total}}` | Suma totala a comenzii |
| `{{email}}` | Adresa de email a clientului |

**Exemplu de mesaj:**

> Buna {{billing_first_name}}, comanda ta #{{order_number}} in valoare de {{order_total}} a fost expediata! Multumim ca ai cumparat de la noi.

### Numere de Urmarire Curier (AWB)

Daca folositi unul dintre plugin-urile de curierat romanesti suportate (Cargus, SameDay sau FanCourier), puteti include numarul de urmarire (AWB) in mesajele SMS. Aceste variabile folosesc blocuri conditionale - continutul din interior apare doar daca exista un numar de urmarire:

**Exemplu cu Cargus:**

> Buna {{billing_first_name}}, comanda ta #{{order_number}} a fost expediata.{{if_cargus_awb}} Numarul tau de urmarire este {{cargus_awb}}.{{endif_cargus_awb}}

Acelasi tipar functioneaza pentru SameDay (`{{if_sameday_awb}}...{{sameday_awb}}...{{endif_sameday_awb}}`) si FanCourier (`{{if_fancourier_awb}}...{{fancourier_awb}}...{{endif_fancourier_awb}}`).

Mesajele SMS cu AWB **nu se trimit automat**. Aceasta este prin design, deoarece implementarile plugin-urilor de curierat variaza foarte mult intre magazine si au multe personalizari. In schimb, pentru fiecare curier suportat, cand o comanda are un numar AWB atasat, o optiune **Send SMS** apare in dropdown-ul **Order Actions** (coltul din dreapta sus al paginii de administrare a comenzii). Administratorul poate declansa manual SMS-ul de acolo. Daca este necesar, dezvoltatorii magazinului pot extinde aceasta functionalitate pentru a trimite automat SMS-uri cu AWB in functie de logica de business specifica a magazinului.

### Testare SMS

In partea de jos a paginii SMS, veti gasi un formular de test. Introduceti un numar de telefon si un mesaj, apoi faceti click pe **Send Test SMS** pentru a verifica ca totul functioneaza inainte de a activa.

---

## Formulare Elementor (Elementor Pro)

Daca site-ul foloseste widget-ul Form din Elementor Pro, plugin-ul poate abona trimiterile catre o lista Newsman si poate trimite valorile celorlalte campuri ca proprietati de abonat. Functionalitatea se aplica oriunde este folosit widget-ul Form, inclusiv in formularele plasate in popup-uri Elementor.

> Widget-ul Form face parte din Elementor Pro, nu din plugin-ul Elementor gratuit. Daca este instalat doar Elementor gratuit, sectiunea Newsman descrisa mai jos nu va aparea.

> **Testat cu:** Elementor si Elementor Pro **3.35.x** si **4.x**, inclusiv formulare plasate in popup-uri Elementor. Integrarea cu widget-ul Form clasic se aplica pentru ambele versiuni; integrarea Atomic Forms (descrisa mai jos) se aplica doar pentru **4.x**, deoarece Atomic Forms nu exista in 3.35.x.

### Setari per Formular

Deschideti orice pagina in editorul Elementor si selectati un widget Form. In tab-ul **Content**, dupa sectiunea **Form Fields**, veti vedea o noua sectiune **Newsman** cu doua optiuni:

- **Send to Newsman** - Dezactivat implicit. Activati pentru a porni Newsman pe acest formular. Cand este dezactivat, nicio data din formular nu este trimisa catre Newsman, indiferent de optiunile per camp de mai jos.

- **Newsman List** - Vizibil doar cand **Send to Newsman** este activat. Alegeti lista Newsman care va primi trimiterile din acest formular. Dropdown-ul este populat automat din contul dvs. Newsman folosind API Key si User ID configurate in **NewsMAN > Settings**, si este pus in cache 10 minute. Daca dropdown-ul este gol, vedeti nota de depanare de la finalul acestei sectiuni.

### Setari per Camp

Fiecare camp din formular are doua optiuni suplimentare in tab-ul **Advanced**:

- **Send to Newsman** - Activat implicit pentru campurile noi. Cand este activat, valoarea acelui camp este trimisa catre Newsman ca proprietate de abonat. **ID**-ul campului (setat in **Advanced > Custom ID**) devine cheia proprietatii in Newsman, asa ca folositi ID-uri stabile in snake_case precum `phone`, `company` sau `birthdate`.

- **Use as Newsman email** - Dezactivat implicit. Bifati exact un camp cu aceasta optiune pentru a indica ce camp contine adresa de email a abonatului. Doar tipurile **Email** si **Text** pot fi marcate. Daca niciun camp nu este marcat, sau campul marcat este gol la trimitere, formularul va esua cu un mesaj de eroare afisat in pagina.

> Sugestie: **Custom ID**-ul unui camp Elementor este valoarea setata in **Advanced > Custom ID**. ID-urile implicite sunt auto-generate (de exemplu `field_a1b2c3d`) si nu sunt usor de citit in Newsman. Recomandam sa setati un Custom ID semnificativ pentru fiecare camp marcat cu **Send to Newsman**.

### Ce se intampla la trimitere

Cand un vizitator trimite formularul:

1. Daca **Send to Newsman** este dezactivat, nu se intampla nimic.
2. Daca este activat, plugin-ul citeste campul de email si valorile proprietatilor per camp, apoi aboneaza (sau reactualizeaza) emailul in lista selectata. Proprietatile abonatului in Newsman sunt actualizate cu valorile campurilor.
3. Daca API-ul Newsman respinge cererea (email lipsa, lista invalida, credentiale expirate), formularul nu afiseaza starea de succes - vizitatorul vede un mesaj de eroare in pagina, iar actiunile native Elementor (de exemplu redirect sau email de confirmare) nu se executa. Eroarea tehnica este de asemenea scrisa in logurile WooCommerce daca logarea este activata.

### Limitari

- Aceasta integrare vizeaza widget-ul **Form clasic**. Noul widget **Atomic Forms** din Elementor 4.x foloseste o arhitectura diferita si este tratat separat in sectiunea **Atomic Forms** de mai jos.
- Formularele din popup-urile Elementor functioneaza la fel - integrarea este pe widget-ul Form in sine, nu pe pagina sau popup-ul care il contine.
- Formularele multi-step functioneaza; sectiunea Newsman se aplica formularului in ansamblu, nu per pas.

### Depanare: dropdown Newsman List gol

Daca dropdown-ul **Newsman List** este gol in editorul Elementor:

1. Confirmati ca **NewsMAN > Settings** arata o conexiune API valida (indicator verde langa credentiale).
2. Dropdown-ul cu liste este pus in cache 10 minute per site. Daca ati schimbat recent credentialele, asteptati pana la 10 minute sau stergeti tranzientele site-ului pentru ca acel cache sa se reincarce.
3. Conturile Newsman au intotdeauna cel putin o lista, deci un dropdown gol indica aproape intotdeauna o problema de credentiale, nu un cont gol.

---

## Atomic Forms (Elementor 4.x, experimental)

Elementor 4.x a introdus **Atomic Forms**, o arhitectura separata in care formularul, etichetele, input-urile, textareas, checkbox-urile si butonul de submit sunt fiecare widget-uri atomice independente, aranjate direct pe canvas. Plugin-ul Newsman suporta Atomic Forms cu acelasi switcher, dropdown de lista si setari per camp ca widget-ul Form clasic, cu o limitare semnificativa mentionata mai jos.

> Atomic Forms este controlat de doua experimente Elementor (`Atomic Widgets` in core si `Atomic Form` in Pro). Ambele sunt active implicit in Elementor 4.x, dar pot fi dezactivate din **Elementor > Settings > Features**. Sectiunea Newsman nu va aparea daca oricare dintre experimente este dezactivat.

> Atomic Forms este in stadiu de dezvoltare (`RELEASE_STATUS_DEV`). Elementor poate schimba API-ul subiacent in orice versiune minora. Daca o actualizare viitoare Elementor strica sectiunea Newsman din Atomic Forms, integrarea cu widget-ul Form clasic nu este afectata si va functiona in continuare.

### Setari per Formular

Deschideti o pagina care contine un Atomic Form. Faceti click pe containerul formularului (elementul parinte care contine input-urile) pentru a-l selecta. Sub sectiunile **Content** si **Settings** existente in panoul editorului, apare o noua sectiune **Newsman** cu doua optiuni:

- **Send to Newsman** - Dezactivat implicit. Activati pentru a porni Newsman pe acest formular. Cand este dezactivat, nu se trimite nicio data catre Newsman, indiferent de setarile per input.

- **Newsman List** - Alegeti lista Newsman care va primi trimiterile. Dropdown-ul este partajat cu integrarea Forms clasica: listele provin din contul dvs. Newsman folosind API Key si User ID configurate in **NewsMAN > Settings**, cu cache de 10 minute.

### Setari per Input

Faceti click pe orice widget **Input**, **Textarea** sau **Checkbox** din interiorul formularului. Panoul editorului pentru acel widget primeste o sectiune **Newsman** cu:

- **Send to Newsman** - Activat implicit. Cand este activat, valoarea trimisa de acest widget este inclusa ca proprietate de abonat. Cheia proprietatii este **ID**-ul widget-ului (setat in sectiunea **Settings**, controlul etichetat **ID**); recomandam sa setati un ID stabil, in snake_case, precum `phone`, `company` sau `birthdate`.

- **Use as Newsman email** - Dezactivat implicit. Bifati exact un input sau textarea cu aceasta optiune pentru a indica ce widget contine adresa de email a abonatului. Widget-ul Checkbox nu expune aceasta optiune (un checkbox nu poate fi campul de email).

> **ID**-ul widget-ului in Atomic Forms este acelasi camp folosit ca atribut HTML `name` la trimiterea formularului. Cele doua trebuie sa coincida pentru ca integrarea sa gaseasca valoarea, motiv pentru care folosim ID-ul direct.

### Ce se intampla la trimitere

Cand un vizitator trimite formularul:

1. Daca **Send to Newsman** este dezactivat la nivel de formular, nu se intampla nimic.
2. Daca este activat, plugin-ul citeste setarile salvate ale formularului, identifica input-ul de email dupa marcajul **Use as Newsman email**, construieste o harta de proprietati din input-urile marcate **Send to Newsman** si aboneaza emailul in lista aleasa cu acele proprietati.

### Limitare: erorile nu sunt afisate in pagina (v1)

Spre deosebire de integrarea cu widget-ul Form clasic, **Atomic Forms nu permite integrarilor terte sa scrie mesaje de eroare in raspunsul formularului**. Fluxul AJAX de trimitere ruleaza propriul action runner Elementor (Email, Webhook, Collect submissions), iar lista hardcodata de actiuni Elementor blocheaza Newsman de la a se inregistra ca actiune reala. Drept urmare, ascultam in paralel cu fluxul oficial in loc sa rulam in interiorul lui.

Consecinta practica: daca Newsman esueaza (email invalid, lista neconfigurata, credentiale expirate, eroare de retea), formularul **va afisa in continuare starea de succes** vizitatorului. Eroarea este inregistrata in logurile WooCommerce (cand WooCommerce este instalat) sub sursa Newsman. Instalarile WordPress simple nu au destinatie de log - verificati credentialele din **NewsMAN > Settings** daca trimiterile Newsman par sa se piarda silentios.

Asteptam ca Elementor sa extinda API-ul de extensie pentru Atomic Forms intr-o versiune viitoare; vom afisa erorile in pagina la acel moment.

### Depanare

- **Sectiunea Newsman nu apare**: confirmati ca ambele experimente atomic sunt active in **Elementor > Settings > Features**. Atat `Atomic Widgets` cat si `Atomic Form` trebuie sa fie pornite.
- **Dropdown-ul Newsman List este gol**: la fel ca la integrarea Forms clasica - vedeti nota de depanare din sectiunea **Formulare Elementor** de mai sus.
- **Emailul nu apare in Newsman**: asigurati-va ca exact un input/textarea are **Use as Newsman email** activat, si ca acelasi widget are un **ID** semnificativ.

---

## Contact Form 7

Daca site-ul foloseste plugin-ul [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), plugin-ul Newsman poate abona trimiterile catre o lista Newsman si poate trimite valorile campurilor pe care le alegeti ca proprietati de abonat.

> Integrarea este controlata de comutatorul **Use Contact Form 7 Integration** din **NewsMAN > Settings > Developer Settings**. Comutatorul (si tab-ul Newsman pe care il controleaza) apare doar cand Contact Form 7 este instalat.

### Setari per formular

In WP admin, deschideti **Contact > Contact Forms** si dati click pe orice formular pentru a-l edita. Un nou tab **Newsman** apare langa tab-urile **Form**, **Mail**, **Messages** si **Additional Settings**, cu urmatoarele optiuni:

- **Send to Newsman** - Dezactivat implicit. Activati-l pentru a porni Newsman pentru acest formular. Cand este dezactivat, nu se trimit date catre Newsman, indiferent de setarile per camp de mai jos.

- **Newsman list** - Alegeti lista Newsman care va primi trimiterile. Dropdown-ul este populat automat din contul Newsman folosind API Key-ul si User ID-ul configurate in **NewsMAN > Settings**, si este memorat in cache 10 minute (cache-ul este partajat cu integrarea Elementor). Daca dropdown-ul este gol sau afiseaza "No Newsman lists are available", verificati credentialele.

- **Email field** - Alegeti care form-tag detine adresa de email a abonatului. **Orice** tip de camp poate fi folosit aici (text, tel, url, email, number, ...), nu doar tag-urile `[email]` - util pentru formulare care colecteaza emailul printr-un camp text cu validare proprie. Selectia implicita este primul form-tag `[email]` din template; daca nu exista un tag email, este selectat primul camp disponibil. Dropdown-ul afiseaza si basetype-ul fiecarui tag langa nume pentru a usura selectia.

- **Send as properties** - O lista de checkbox-uri cu toate form-tag-urile din template. Fiecare camp bifat este trimis catre Newsman ca proprietate de abonat avand ca cheie numele form-tag-ului. Campul email selectat este afisat dar dezactivat (nu poate fi proprietate, deoarece este deja folosit ca email de abonat). Implicit: fiecare tag non-sistem cu exceptia campului email este bifat.

> Tag-urile interne (butonul Submit, file uploads, acceptance, reCAPTCHA, captcha-c, captcha-r, quiz) sunt excluse atat din dropdown-ul de email cat si din checkbox-urile de proprietati - nu poarta valori introduse de utilizator care sa aiba sens ca date de abonat.

### Ce se intampla la trimitere

Cand un vizitator trimite formularul cu succes:

1. Daca **Send to Newsman** este dezactivat, nu se intampla nimic.
2. Daca este activat, plugin-ul citeste emailul din campul email configurat, construieste o harta de proprietati din campurile bifate (campurile cu valori multiple precum checkbox-urile sunt codate ca JSON) si aboneaza (sau reimprospateaza) emailul in lista Newsman aleasa cu acele proprietati.
3. Fluxul normal al formularului (trimitere mail, mesaj de confirmare, redirect) nu este afectat. O eroare de la API-ul Newsman este inregistrata in log dar **nu** blocheaza mail-ul formularului si nu schimba ce vede vizitatorul - formularul afiseaza in continuare starea de succes.

### Limitare: erorile nu sunt afisate in pagina (v1)

Contact Form 7 nu ofera infrastructura nativa pentru integrari terte care sa afiseze mesaje de eroare in raspunsul formularului. Esecurile de abonare Newsman (email invalid, credentiale expirate, eroare de retea) sunt scrise in logurile WooCommerce sub sursa Newsman (cand WooCommerce este instalat). Instalarile WordPress simple nu au destinatie de log - verificati credentialele din **NewsMAN > Settings** daca trimiterile Newsman par sa se piarda silentios.

### Depanare

- **Tab-ul Newsman nu apare**: confirmati ca Contact Form 7 este activ si ca **Use Contact Form 7 Integration** este pornit in **NewsMAN > Settings > Developer Settings**.
- **Dropdown-ul Newsman list este gol**: la fel ca la integrarea Elementor - confirmati credentialele in **NewsMAN > Settings** si asteptati pana la 10 minute pentru reimprospatarea cache-ului.
- **Emailul nu apare in Newsman**: confirmati ca dropdown-ul **Email field** indica un tag pe care vizitatorul il completeaza efectiv. Daca campul este gol la momentul trimiterii, randul este omis si se scrie o intrare de log de tip debug.

---

## Intrebari Frecvente

### Am nevoie de WooCommerce pentru a folosi acest plugin?

Nu. Plugin-ul functioneaza cu un site WordPress standard pentru abonare la newsletter si remarketing de baza. Totusi, checkbox-ul de checkout, notificarile SMS, urmarirea comenzilor si functionalitatea de export clienti necesita WooCommerce.

### Care este diferenta intre Opt-in si Double Opt-in?

- **Opt-in**: Abonatul este adaugat in lista imediat cand trimite adresa de email.
- **Double Opt-in**: Abonatul primeste un email cu un link de confirmare. Este adaugat in lista doar dupa ce face click pe acel link. Aceasta asigura ca adresa de email este valida si ca persoana chiar doreste sa se aboneze. Double Opt-in este recomandat pentru conformitatea GDPR.

### Cum gasesc Form ID-ul pentru Double Opt-in?

1. Autentificati-va in [contul Newsman](https://newsman.app).
2. Selectati **Lista** si accesati **Settings > Subscription forms**.
3. **Creati** sau **Editati** un formular.
4. Selectati **Landing page** si **Activate for newsletter subscription**.
5. Selectati **Embedded form** - Form ID-ul va fi afisat acolo. Copiati-l si inserati-l in campul **Confirmation email Form ID** din setarile plugin-ului.

### M-am conectat prin OAuth dar listele sunt goale. Ce ar trebui sa fac?

Dropdown-ul de liste este populat printr-o cerere API catre Newsman folosind API Key si User ID. Daca dropdown-ul este gol, inseamna ca conexiunea la Newsman nu functioneaza. Accesati **NewsMAN > Settings** si verificati ca API Key si User ID sunt corecte si ca indicatorul de stare arata o conexiune valida. Fiecare cont Newsman are cel putin o lista implicit, deci daca credentialele sunt corecte, listele vor aparea.

### Pot trimite SMS clientilor din comenzile anterioare?

Functionalitatea SMS se declanseaza doar pentru schimbarile noi de status ale comenzilor de acum incolo. Nu trimite retroactiv mesaje pentru comenzile anterioare. Totusi, puteti folosi functionalitatea de **Export** din pagina Sync pentru a trimite numerele de telefon existente ale clientilor catre lista SMS Newsman, si apoi sa trimiteti campanii SMS in masa din dashboard-ul Newsman.

### Ce este Action Scheduler si am nevoie de el?

Action Scheduler este un sistem care proceseaza sarcini in fundal in loc de imediat. Este inclus implicit in cele mai recente instalari WooCommerce, deci daca rulati WooCommerce cel mai probabil il aveti deja. Daca magazinul dvs. are trafic mare si observati ca checkout-ul este lent dupa activarea Newsman, il puteti activa in Setarile pentru Dezvoltatori pentru a procesa abonarile si dezabonarile in fundal. Pentru majoritatea magazinelor, acest lucru nu este necesar.

### Unde sunt logurile plugin-ului?

Plugin-ul foloseste sistemul de logare WooCommerce, deci logurile sunt disponibile doar daca WooCommerce este instalat. Le puteti gasi in **WooCommerce > Status > Logs**. Nivelul de logare este controlat din Setarile pentru Dezvoltatori ale plugin-ului. Daca intampinati probleme, setati nivelul de logare la **Debug**, reproduceti problema, apoi verificati logurile WooCommerce pentru mesaje de eroare. Pe o instalare WordPress simpla fara WooCommerce, plugin-ul nu inregistreaza loguri.
