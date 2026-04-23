# Sprava ubytovani na koleji
## Co to musi delat?
* Pridelovani pokoju
* Evidence studentu
* Evidence plateb
---
## Fungování
### `index.php`
#### Infografika
- pripoji se k databazi
- spocita pocet vsech mist 
- vypocita % volnych mist 
    - hard coded 2500 max kapacita
    - aktuakni/max
#### login page
- pomoci get se preda argument role ktera je budto `admin` nebo `student`
- tyto argumenty se predaji v domene
- napriklad: `?role=student`
- POST se nepouziva aby si admini i studenti mohli bookmarknout ten login
