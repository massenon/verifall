import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

/*
 * LES IDENTIFIANTS DE SIGNATURE, LUS HORS DU DÉPÔT.
 *
 * `android/key.properties` n'est pas versionné (`.gitignore`), conformément au
 * §7.1 du document de livraison : « les identifiants de signature arrivent par
 * variables d'environnement ou par un fichier de propriétés non versionné — le
 * §5 des règles du chantier, jamais de secret en dur, ne s'arrête pas à PHP ».
 *
 * Le modèle à recopier est `android/key.properties.exemple`.
 */
val fichierDeCles = rootProject.file("key.properties")
val clesDeSignature = Properties().apply {
    if (fichierDeCles.exists()) {
        fichierDeCles.inputStream().use { load(it) }
    }
}
val signatureDisponible = fichierDeCles.exists() &&
    clesDeSignature.getProperty("storeFile") != null

android {
    namespace = "com.verifall.verifall_app"
    compileSdk = flutter.compileSdkVersion
    /*
     * NDK NON DÉCLARÉ, ET C'EST DÉLIBÉRÉ.
     *
     * `flutter.ndkVersion` fait exiger par le greffon Android une version
     * précise du NDK, que Gradle tente alors d'INSTALLER au moment de la
     * configuration — avant même de savoir si le projet en a besoin.
     *
     * Or aucune dépendance de ce projet ne compile de code natif depuis les
     * sources : les greffons employés (drift, video_player, secure_storage,
     * pusher) livrent du Kotlin ou des bibliothèques déjà compilées. Le NDK
     * n'était réclamé que par cette ligne.
     *
     * Si un greffon venait à en avoir besoin, la construction le dirait
     * clairement plutôt que d'échouer sur un téléchargement. On rétablirait
     * alors la ligne en connaissance de cause.
     */

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "com.verifall.verifall_app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        // `chemin` est extrait dans un `val` NON NUL avant l'appel à `file()` :
        // `getProperty` rend un `String?`, et `Project.file(Any)` n'accepte pas
        // de nullable — le passer directement ne compile pas, même à l'intérieur
        // du `if` qui garantit sa présence.
        val chemin = clesDeSignature.getProperty("storeFile")

        if (signatureDisponible && chemin != null) {
            create("publication") {
                storeFile = file(chemin)
                storePassword = clesDeSignature.getProperty("storePassword")
                keyAlias = clesDeSignature.getProperty("keyAlias")
                keyPassword = clesDeSignature.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            /*
             * CE QUI A CHANGÉ, ET POURQUOI CELA COMPTE PLUS QU'IL N'Y PARAÎT.
             *
             * La ligne d'origine — `signingConfigs.getByName("debug")` — venait
             * du gabarit Flutter. Elle rend `flutter run --release` commode, et
             * elle est SILENCIEUSE : rien, dans la sortie de construction, ne
             * dit qu'on vient de produire un artefact signé avec la clé de
             * débogage. Google Play le refuse, ce qui est le cas heureux ; le
             * cas malheureux est un APK distribué à la main, que plus aucune
             * mise à jour ne pourra jamais remplacer — la clé de signature d'une
             * application Android est définitive.
             *
             * On garde donc la commodité locale, et on ferme le chemin de
             * publication : voir la vérification `bundleRelease` plus bas.
             */
            signingConfig = if (signatureDisponible) {
                signingConfigs.getByName("publication")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

/*
 * LE GARDE-FOU : un bundle de publication ne part pas signé en débogage.
 *
 * On vise `bundleRelease` — la tâche derrière `flutter build appbundle`, donc
 * le format de publication exigé au §7.1 — et NON `assembleRelease`, qui sert
 * au `flutter run --release` d'un poste de développement sans magasin de clés.
 * La distinction est délibérée : interdire les deux rendrait le test local
 * impossible pour toute l'équipe, et une règle qui gêne le travail quotidien
 * finit contournée.
 */
gradle.taskGraph.whenReady {
    /*
     * Comparaison EXACTE, et non `contains`.
     *
     * Le premier essai cherchait « bundleRelease » en sous-chaîne. AGP crée ses
     * propres tâches internes — `bundleReleaseResources`, `bundleReleaseClasses`,
     * `bundleReleaseJsAndAssets` — et elles figurent dans le graphe d'un simple
     * `flutter build apk`. Le garde-fou refusait donc AUSSI la construction
     * d'APK, qu'il n'a pas à bloquer.
     *
     * La tâche de publication s'appelle exactement `bundleRelease`.
     */
    val publie = allTasks.any { it.name == "bundleRelease" }

    if (publie && !signatureDisponible) {
        throw GradleException(
            """
            |
            |  Bundle de publication demandé sans magasin de clés.
            |
            |  `android/key.properties` est absent : la construction produirait un
            |  artefact signé avec la CLÉ DE DÉBOGAGE. Play le refuserait, et un
            |  artefact ainsi distribué ne pourrait plus jamais être mis à jour.
            |
            |  Voir `android/key.properties.exemple`, et activer la « signature
            |  d'application Play » : Google conserve alors la clé de
            |  distribution, et une clé de téléversement perdue se remplace.
            |
            """.trimMargin()
        )
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
