TSL-modulen för Wordpress

De här kraven måste vara uppfyllda för att systemet ska fungera:

- Varje TSL-tävling består av flera separata formulär. Varje formulär måste 
  ha en "form_key" enligt detta format: "tsl_<tävlingens namn>_<formulärets namn>", 
  ex. _tsl_2018_signups_.
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