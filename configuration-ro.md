# Plugin Newsman pentru WordPress - Ghid de Configurare

Acest ghid prezinta toate setarile din plugin-ul Newsman, pentru a va putea conecta magazinul WordPress sau WooCommerce la contul Newsman si a incepe sa colectati abonati, sa trimiteti newslettere si sa urmariti comportamentul clientilor.

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
3. Veti fi redirectionat catre site-ul Newsman. Autentificati-va daca este necesar si acordati acces.
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

- **Allow API access** - Activati aceasta optiune daca doriti ca Newsman sa poata prelua date (precum produse sau abonati) direct din magazinul dvs. Aceasta este necesara pentru functionalitati precum feed-urile de produse in newslettere. Lasati-o dezactivata daca nu aveti nevoie de ea.

- **Send User IP Address** - Cand un vizitator se aboneaza, plugin-ul poate trimite adresa IP a acestuia catre Newsman. Acest lucru poate ajuta la analiza si conformitate. Daca dezactivati aceasta optiune, plugin-ul va folosi in schimb **Server IP Address** pe care il introduceti mai jos.

- **Server IP Address** - O adresa IP de rezerva folosita cand "Send User IP Address" este dezactivata. De obicei puteti lasa acest camp gol.

- **Import Authorize Header Name / Key** - Aceasta este o optiune veche (legacy) pentru protejarea feed-ului de produse cu credentiale de securitate personalizate. Daca v-ati conectat prin OAuth, nu trebuie sa le setati - plugin-ul gestioneaza autentificarea automat. Trebuie sa le completati doar daca ati configurat conexiunea manual si doriti sa adaugati un nivel suplimentar de securitate la importurile de feed-uri de produse.

### Abonare la Newsletter

- **Newsletter Opt-in type** - Alegeti cum sunt adaugati noii abonati:
  - **Opt-in** - Abonatul este adaugat imediat.
  - **Double Opt-in** - Abonatul primeste mai intai un email de confirmare si trebuie sa faca click pe un link pentru a confirma. Aceasta optiune este recomandata pentru conformitatea GDPR.

- **Confirmation email Form ID** - Daca ati ales Double Opt-in mai sus, introduceti Form ID-ul din contul dvs. Newsman. Acesta spune Newsman ce sablon de email de confirmare sa trimita. Puteti gasi acest ID in dashboard-ul Newsman la sectiunea Forms.

### Optiuni Checkout (doar WooCommerce)

Aceste setari adauga un checkbox de newsletter pe pagina de checkout a magazinului, astfel incat clientii se pot abona in timp ce plaseaza o comanda.

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
