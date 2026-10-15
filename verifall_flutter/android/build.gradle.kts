allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
/*
 * ALIGNE LE `compileSdk` DES GREFFONS SUR CELUI DE L'APPLICATION.
 *
 * ## Le blocage que cela lève
 *
 * `flutter_secure_storage` 11 déclare `compileSdk = 37`. Or Flutter fixe 36 par
 * défaut (`FlutterExtension.compileSdkVersion`), et l'API 37 n'existe pas encore
 * comme plateforme stable : le gestionnaire de SDK ne connaît que la
 * PRÉVERSION `android-37.0`, tandis que Gradle réclame la chaîne `android-37`.
 * `sdkmanager "platforms;android-37"` répond « Failed to find package ».
 *
 * La construction échoue donc sur :
 *
 *     Failed to find target with hash string 'android-37'
 *
 * et **aucune version d'APK ne peut sortir** tant que ce n'est pas traité. Ce
 * n'est pas un défaut du projet : c'est un greffon qui vise une API que le
 * gestionnaire de SDK ne publie pas encore.
 *
 * ## Pourquoi aligner vers le BAS est sans risque ici
 *
 * `compileSdk` dit « contre quelles signatures on compile », jamais « sur
 * quelles versions on tourne » — c'est `targetSdk` et `minSdk` qui décident de
 * cela, et ils ne bougent pas. Un greffon de stockage sécurisé n'emploie aucun
 * symbole introduit en API 37 : il s'adosse au `KeyStore` Android, stable
 * depuis bien plus longtemps.
 *
 * Si un jour un greffon a réellement besoin d'un symbole d'API 37, la
 * compilation le dira nommément — et ce sera le moment de retirer ce bloc,
 * l'API 37 étant alors stabilisée.
 *
 * ## L'ORDRE DE CE BLOC COMPTE — ne pas le déplacer plus bas
 *
 * Il doit précéder `subprojects { evaluationDependsOn(":app") }`. Cette
 * ligne-là force l'évaluation des sous-projets ; si notre `afterEvaluate`
 * s'enregistrait après elle, Gradle refuserait net :
 *
 *     Cannot run Project.afterEvaluate(Action) when the project is already evaluated.
 */
/*
 * La valeur est ÉCRITE, et non lue de `flutter.compileSdkVersion` : l'extension
 * `flutter` est enregistrée par le greffon Gradle de Flutter, qui n'est appliqué
 * que dans `:app`. Elle n'existe pas ici.
 *
 * 36 est la valeur de `FlutterExtension.compileSdkVersion` du SDK installé —
 * donc celle que `:app` emploie déjà. Les deux doivent rester alignées : si une
 * mise à jour de Flutter monte la sienne, la construction se remettra à échouer
 * sur le même message, et c'est ce chiffre-ci qu'il faudra suivre.
 */
val compileSdkAligne = 36

subprojects {
    afterEvaluate {
        val extensionAndroid = project.extensions.findByName("android") ?: return@afterEvaluate

        /*
         * Appel par RÉFLEXION plutôt que par un type d'AGP.
         *
         * `com.android.build.gradle.BaseExtension` n'est pas garanti sur le
         * chemin de classes du projet racine : `settings.gradle.kts` déclare le
         * greffon avec `apply false`, ce qui le résout sans l'appliquer ici. Un
         * `is BaseExtension` ne compilerait pas.
         *
         * La réflexion évite d'ajouter AGP au `buildscript` de la racine pour
         * trois lignes — une dépendance de construction en plus, à tenir à jour,
         * pour contourner un problème qui disparaîtra quand l'API 37 sera
         * stabilisée.
         */
        val poser = extensionAndroid.javaClass.methods.firstOrNull {
            it.name == "compileSdkVersion" &&
                it.parameterTypes.size == 1 &&
                it.parameterTypes[0] == Int::class.javaPrimitiveType
        }

        poser?.invoke(extensionAndroid, compileSdkAligne)
    }
}

subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
