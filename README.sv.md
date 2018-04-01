# TSL-modulen för Wordpress

## Systemkrav

- Wordpress-installation med tillägget Formidable Forms

## Användning

De här kraven måste vara uppfyllda för att systemet ska fungera:

- Varje TSL-tävling består av flera separata formulär. Varje formulär måste 
  ha en "form_key" enligt detta format: "tsl-<tävlingens namn>-<formulärets namn>", 
  ex. _tsl-2018-signups_.
- Varje poängsatt uppgift eller kontroll måste vara en egen fråga.
- Varje fråga måste ha en unik nyckel ("field_key")
- Ett fält i varje formulär måste specificera en patrull på detta sätt:
    - antingen via ett "användar-id-fält" eller via ett "lookup-fält" som kopplas 
    till ett patrullnamn.
    - fältet måste ha en unik nyckel ("field_key") som börjar med "team".
    - för att "användar-id-fält" ska fungera måste patrullnamnet anges som antingen "nick name" eller "display name".
- För att underlätta inrapporting så kan ett formulär bäddas in i ett annat men
  tänk på det bara är formulär med patrullnamn som ett fält som används vid
  poängberäkning. Om formulär A innehåller formulär B, där formulär B har ett 
  "patrullfält", så ska ... innan svar anges i formulär A. Formulär A ska bara 
  användas för att presentera flera formulär B på samma sida.
- Varje tävling måste ha ett anmälningsformulär med tre specifika fält:
  - Lista med åldersgrupper med field_key="age_group"
  - Textfält för patrullnamn med field_key="team_name"
  - Textfält för telefonnummer med field_key="phone_primary" resp. field_key="phone_secondary"
  - Fält som lagrar användarnamnet på den som skickar in anmälningen (varje patrull antas ha 
    skapat ett användarkonto i Wordpress, och loggat in, innan de går till anmälningsformuläret).
- Patrullens användare i Wordpress identifieras genom att patrullens namn anges som användarens smeknamn

Funktionalitet i Wordpress administrationssida:

- Lista med tävlingar.
- Lista med lag som deltar i en tävling. Här visas även hur stor del av delmomenten som resp. lag har fullföljt.
- Konfiguration av korrekta svar för resp. formulär
- Resultatlista med poängsumma per lag
- Fullständig lista med ett lags alla svar. Här ges även möjlighet att korrigera den utdelade poängen för respektive fråga.
  
## Utveckling

### Starta lokal Wordpress-installation med Docker

Kopiera Formidable Forms (/var/www/html/wp-content/plugins/formidable) från ```tunnelbanejakten.se``` till denna mapp:

    ./docker/base/html/wp-content/plugins/formidable

Du ska inte behöva ange något licensnyckel för Formidable Forms eftersom den ska fungera 
fullt ut så länge domänen är "localhost".

Starta webbserver och databas:

    docker-compose up
    
Surfa till startsidan:
    
    http://localhost:8081/wp-admin
    
Första gången du startar applikationen måste du konfigurera Wordpress. Det handlar främst om att 
sätta ett lösenord på ditt lokala adminstratörskonto.

Klicka på "Tunnelbanejakten" längst ner i Wordpress-menyn.

### Uppdatera Wordpress-installation på tunnelbanejakten.se

0. Fråga efter inloggningsuppgifter från systemadministratör. 
   Din användare ska vara medlem i `www-data`.
   
0. Uppdatera filerna i `/var/www/html/wp-content/plugins/tsl`  