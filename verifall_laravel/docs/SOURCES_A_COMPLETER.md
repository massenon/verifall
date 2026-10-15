# Sources dont le domaine est resté incomplet

Relevé le 9 septembre 2026, en marge de {@see SourcesVeraSeeder}. **55 entrées** du répertoire public de Vera n'ont pas été posées en base, et
aucune ne l'a été de travers : leur domaine est illisible à la source.

## Pourquoi elles manquent

La page range ses URL dans une colonne à largeur fixe, qui les **coupe dans le
DOM**. Ce n'est pas un défaut de la capture : le texte complet n'est nulle part
dans la page, et aucun agrandissement ne le ramène.

Deux formes se rencontrent :

- l'hôte s'arrête en plein milieu — `https://www.conspiracywat` ;
- l'hôte a l'air complet mais son TLD est rogné — `science.feedback.or`,
  `nationalgeographic.con`, `pubmed.ncbi.nlm.nih.go`.

La seconde est la plus dangereuse, parce qu'elle **passe une validation
naïve** : `.or` ressemble à un TLD. Sans ce contrôle, 17 fiches seraient
entrées avec un domaine faux.

## Pourquoi elles n'ont pas été devinées

`science.feedback.or` est très probablement `sciencefeedback.co`, et
`nationalgeographic.con` certainement `nationalgeographic.com`. Ces deux-là
se devinent ; d'autres non — et surtout, **le référentiel sert au moteur à
créditer une source**. Une fiche dont le domaine est faux ne reste pas une
imprécision de catalogue : elle accorde de la crédibilité à un domaine qui
n'est pas celui qu'on croit, ou elle en refuse au vrai. Une fiche absente se
voit ; une fiche fausse, non.

## Comment en reprendre une

Depuis le back-office, écran **Sources fiables** : le domaine y est normalisé
par la même règle que le seeder, et l'index unique de `trusted_sources.domain`
refuse un doublon. Rien à faire d'autre que de saisir le domaine complet.

---

## Fact-checking — 25

| Nom affiché | Ce que la source montre | Région |
|---|---|---|
| Abidjan Fact Check | `https://abidjanfactcheck.or` | Côte d'Ivoire |
| Agência Lupa | `https://www.agencialupa.or` | Brazil |
| Beam Reports | `https://www.beamreports.c` | Soudan |
| CableCheck | `https://factcheck.thecable.n` | Nigeria |
| Centrafrique Check | `https://centrafriquecheck.o` | République Centre-africa.. |
| Conspiracy Watch | `https://www.conspiracywat` | France |
| DeFacto Fact-checks | `https://defacto-observatoir` | France |
| Désinfox-Migrations | `https://desinfoxmigrations.` | France |
| Estadão Verifica | `https://www.estadao.com.b` | Brazil |
| GuineeCheck | `https://www.guineecheck.o` | Guinée |
| La Nouvelle République - Désinfox | `https://www.lanouvellerepu` | France |
| Les Surligneurs | `https://www.lessurligneurs.` | France |
| Media Matters | `https://www.mediamatters.` | US |
| NewsGuard's Reality Check | `https://www.newsguardreal` | US |
| Observatoire du conspirationnisme | `https://www.conspiracywat` | France |
| Pagella Politica | `https://www.pagellapolitica` | Italy |
| Pharmacomedicale.org | `https://pharmacomedicale.` | France |
| Piga Firimbi | `https://pigafirimbi.africaunc` | Kenya |
| Rumor Scanner | `https://www.rumorscanner.` | Bangladesh |
| Science Feedback | `https://science.feedback.or` | France / International |
| SciencePresse-détecteur rumeurs | `https://www.sciencepresse.` | Canada |
| Studio Sifaka - I-Verify | `https://www.studiosifaka.or` | Africa |
| The Washington Post Fact Checker | `https://www.washingtonpo` | US |
| TogoCheck | `https://www.togocheck.cor` | Togo |
| TV5Monde-Avraidire | `https://information.tv5mon` | France / International |

## Publication de référence — 17

| Nom affiché | Ce que la source montre | Région |
|---|---|---|
| Agence SciencePresse | `https://www.sciencepresse.qc.c` | Canada |
| Alternatives Économiques | `https://www.alternatives-econc` | France |
| Borneo Bulletin | `https://www.borneobulletin.cor` | Anglais |
| Discover | `https://www.discovermagazine.` | US |
| El Universal | `https://www.eluniversal.com.m` | Espagnol |
| European Scientist | `https://www.europeanscientist.` | Europe |
| Frontier Myanmar | `https://www.frontiermyanmar.n` | Myanmar |
| La Maison des Reporters | `https://lamaisondesreporters.s` | Senegal |
| La Nouvelle République | `https://www.lanouvellerepubliq` | France |
| Le Monde informatique | `https://www.lemondeinformatiq` | France |
| National Geographic | `https://nationalgeographic.con` | US |
| Phnom Penh Post | `https://www.phnompenhpost.c` | Khmer |
| Québec Science | `https://www.quebecscience.qc.` | Canada |
| Radio Ndeke Luka | `https://www.radiondekeluka.or` | Afrique |
| Studio Hirondelle RDC | `https://www.studiohirondellerd` | Afrique |
| Studio Kalangou | `https://www.studiokalangou.or` | Nigeria |
| The Globe and Mail | `https://www.theglobeandmail.c` | Anglais |

## Source experte — 13

| Nom affiché | Ce que la source montre | Région |
|---|---|---|
| Assurance-Maladie | `https://www.assurance-maladie` | France |
| Drees-statistique publique santé et soli | `https://drees.solidarites-sante.g` | France |
| Guichet du savoir | `https://www.guichetdusavoir.or` | France |
| Médecine/sciences | `https://www.medecinesciences` | France |
| PubMed (NIH) | `https://pubmed.ncbi.nlm.nih.go` | International |
| Reuters Institute (Oxford University) | `https://reutersinstitute.politics.` | UK-international |
| Santé publique France | `https://www.santepubliquefran` | France |
| SDES-statistique publique environneme | `https://www.statistiques.develc` | France |
| SDES-statistique publique environneme | `https://www.statistiques.develo` | France |
| Smithsonian Magazine | `https://www.smithsonianmag.c` | US |
| Unbiased Science | `https://theunbiasedscipod.subs` | US |
| Vaccine Confidence Project | `https://www.vaccineconfidence` | International |
| Your Local Epidemiologist | `https://yourlocalepidemiologist` | US |

