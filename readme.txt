=== Vasaskrímsli ===
Contributors: sverrirs
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.5.4
License: MIT

Gagnvirkir hlutar vasaskrimsli.is.

== Description ==

* Orðsifjafræði: blokk með leitanlegri töflu yfir uppruna nafna, ritill í stjórnborði (Orðsifjar) og CSV útflutningur/innflutningur.

== Changelog ==

= 1.5.4 =
* Tímaflækingar: merkið segir nú hvort skrímslið er úr fortíðinni eða framtíðinni („Tímaflækingur · Fortíð“ eða „Tímaflækingur · Framtíð“), 11 í hvorum hópi. Leit að „Fortíð“ eða „Framtíð“ finnur hvorn hóp og leit að „Tímaflækingur“ finnur alla 22.

= 1.5.3 =
* Skrímslaskrá: „+N form“ stendur sem látlaus texti neðst í hægra horni spjaldsins, svo það skyggir ekki lengur á myndina.

= 1.5.2 =
* PDF-kort: Fjölskylda stendur í eigin línu fyrir neðan „Vissir þú?“ boxið en ekki inni í því.
* Safnið mitt: Hreinsa spyr „Ertu viss?“ áður en safnið er hreinsað.

= 1.5.1 =
* Fjölskylda: hvert skrímsli sem þróast sýnir nú alla fjölskylduna í Þýðingu, í töflunni, sprettiglugganum og á PDF-kortinu. Línan er búin til úr þróunargögnunum og kemur í stað skrifaðra lína sem vantaði í eða voru með rangt númer.
* Safnið mitt: minni hnappar hlið við hlið, með niðurhals- og ruslatunnutákni. Hreinsa er rauður og spyr fyrst; annar smellur á meðan stikan rennur út hreinsar safnið.

= 1.5.0 =
* Orðsifjafræði: taflan hefur nú þrjá dálka, Nafn, Þýðing og Lýsing. Upprunaorð og enska nafnið (smátt, grátt, með tengli) standa undir nafninu.
* Nýr reitur, Lýsing: hvernig skrímslið lítur út og hvað það gerir, svo nafnið skiljist. Hann er í stjórnborðinu, í CSV út- og innflutningi og í útgáfusögu.
* Gagnaútgáfa 7 setur Lýsingu á öll 1028 skrímslin, aðeins þar sem reiturinn er tómur, og breytir #496 í Vaflanga. Afrit er tekið fyrst og texti sem þú hefur skrifað sjálf(ur) helst óbreyttur.
* Sprettigluggi og PDF-kort: nafn skrímslisins sjálfs er feitletrað í Fjölskyldu-línunni.

= 1.4.6 =
* Sprettiglugginn heldur spjöldunum kyrrum þegar smellt er á form: stóra spjaldið er alltaf skrímslið sjálft og formin í sömu röð. Spjöld í safninu eru merkt „✓ Í safninu“ og það sem síðasti smellur bætti við fær þykkari ramma og „✓ Bætt í safnið“.

= 1.4.5 =
* Safnið mitt: hver smellur á skrímsli bætir næsta formi þess við safnið, svo hægt er að eiga mörg form sama skrímslis. Smellir taka ekkert úr safninu; það gerir × í safnspjaldinu.
* Sprettiglugginn sýnir formið sem var valið sem stóra spjaldið, merkt „Í safninu“, og hak á hinum formunum sem eru þegar í safninu.
* Safnið rúmar nú allt að 50 spjöld.

= 1.4.4 =
* Safnið mitt: smellir aftur á skrímsli velja önnur form þess eitt af öðru (t.d. Þrumumús, Þrumumús Alóla, Mega Þrumumús X) og síðasti smellurinn tekur það úr safninu. Spjaldið, listinn og PDF-kortið sýna valið form.

= 1.4.3 =
* Formspjöld sýna ekki lengur upprunaorð og þýðingu aftur, aðeins mynd og nafn formsins. Nöfn formanna koma nú úr vasaskrimsli-dict.

= 1.4.2 =
* Form skrímsla bera nú íslensk nöfn eins og í bókunum (t.d. Rottóttur Alóla, Funhundur Hisui, Mega Bálormur X) og hvert formspjald sýnir upprunaorð og þýðingu skrímslisins.
* Orðsifjafræði: mynd, númer og nafn eru nú saman í fyrsta dálki töflunnar, með stærri mynd.

= 1.4.1 =
* Öll form skrímslis sjást nú í einu, hvert á sínu spjaldi með stórri mynd, við hlið sprettgluggans (á síma tvö í röð undir honum).

= 1.4.0 =
* Skrímslaskrá: safnið mitt. Smelltu á allt að 20 skrímsli til að safna þeim (smelltu aftur til að taka úr safninu). Safnið er í spjaldi vinstra megin sem rennur út þegar músin fer yfir það, og er geymt í vafranum.
* Safnið má sækja sem PDF skjal: eitt A6 spjald á hverri síðu, með mynd, nafni, númeri, upprunaorðum, Þýðingu, fróðleik („Vissir þú?“) og þróun með myndum og númerum, á litaskala eftir tegund skrímslisins.
* Skrímsli með sérstök form eða svæðisbundin form (t.d. Alóla, Galar, Hisui, Paldea og Mega) eru merkt í myndayfirlitinu, og sérstakt spjald sýnir öll formin þegar músin fer yfir skrímslið.

= 1.3.0 =
* #875 heitir nú Klakagæs (áður Klakageir), með nýjum upprunaorðum og Þýðingu: geirfuglinn var ekki mörgæs. Aðeins skipt um texta sem er enn eins og fyrri útgáfa skrifaði hann; texti sem hefur verið breytt í stjórnborðinu er látinn vera og kemur fram í tilkynningunni.

= 1.2.0 =
* #752 heitir nú Kafekkja (áður Kafló), með nýjum upprunaorðum og Þýðingu; Þýðing #751 Bóluló uppfærð. Aðeins skipt um texta sem er enn eins og fyrri útgáfa skrifaði hann; texti sem hefur verið breytt í stjórnborðinu er látinn vera og kemur fram í tilkynningunni.
* Tímaflækingar: þversagnarskrímslin (#984 til 995, 1005 til 1010 og 1020 til 1023) eru merkt í Orðsifjafræði-töflunni, Skrímslaskrá og sprettglugganum, og finnast með leit að „Tímaflækingur“.

= 1.1.0 =
* Nýjar Þýðingar fyrir 661 skrímsli í kynslóðum 3 til 9: íslenskur orðsifjauppruni og skemmtilegar staðreyndir. Aðeins skipt um texta sem er enn eins og útgáfa 0.3.0 skrifaði hann; texti sem hefur verið breytt í stjórnborðinu er látinn vera og kemur fram í tilkynningunni.
* Afrit er tekið fyrir uppfærsluna og hver breytt færsla fær útgáfu í sögunni.

= 1.0.0 =
* Fyrsta fullbúna útgáfa. Sömu gögn og í 0.3.0 (nýjustu samþykktu nöfnin úr lokalistanum).

= 0.3.0 =
* Nöfn 2026: íslensk nöfn, upprunaorð og þýðingar fyrir 639 skrímsli í kynslóðum 4 til 9 sett inn þar sem enn stóð „–“. Texti sem hefur verið skrifaður í stjórnborðinu er aldrei yfirskrifaður.
* 22 breytt nöfn í 3. kynslóð og Grafíkja² (#233, 2. kynslóð, áður Grafíkja2): aðeins skipt um nafn ef það er enn óbreytt frá útgáfu; annars er færslunni sleppt og það kemur fram í tilkynningunni.
* Kynslóðir 3 til 9 merktar „í vinnslu“ (skýring þar sem engin var, og „(í vinnslu)“ í fyrirsögn 3. kynslóðar).
* Afrit er tekið fyrir uppfærsluna og hver breytt færsla fær útgáfu í sögunni.
* Ný blokk: Skrímslaskrá, myndayfirlit með númeri og nafni, og sprettglugga með færslunni.

= 0.2.1 =
* Smámyndir með gegnsæjum bakgrunni (áður hvítur).
* Stærri mynd birtist þegar músin er yfir smámynd, eða við að smella á hana.

= 0.2.0 =
* Kynslóðir 4 til 9: allar færslur úr landsskrá (númer og enskt heiti með tengli) bætast við.
* Smámynd af hverju skrímsli lengst til vinstri, hýst á eigin vef.
* Skil í Upprunaorð samræmd í langstrik (—), líka við vistun og innflutning.

= 0.1.1 =
* Taflan lítur út eins og upprunalega taflan: rammar, bil og jöfnun dálka.

= 0.1.0 =
* Fyrsta útgáfa: Orðsifjafræði.
