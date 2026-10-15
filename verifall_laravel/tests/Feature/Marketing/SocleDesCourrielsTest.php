<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Enums\VerdictFactCheck;
use App\Mail\OtpCodeMail;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Le socle du système de courriels
|--------------------------------------------------------------------------
|
| Trois exigences de la directive « ULTRA PREMIUM » qui ont ceci de commun
| qu'elles cassent EN SILENCE : rien dans un rendu de client de messagerie
| n'est observable depuis la suite, et un courriel parti de travers ne
| revient pas.
|
|   §37 — chaque courriel doit avoir une version texte brut ;
|   §38 — le preheader complète l'objet dans la boîte de réception ;
|   §40 — le mode sombre doit être anticipé, pas subi.
|
| Le cas le plus vicieux est le troisième, et c'est lui qui justifie ce
| fichier : Laravel INLINE `themes/verifall.css` dans des attributs `style`,
| qui l'emportent sur toute règle de feuille. Un bloc `prefers-color-scheme`
| écrit sans `!important` est donc mort-né — et il le reste sans qu'aucune
| erreur ne se produise nulle part.
|
| ## Pourquoi on part d'un ENVOI, et pas d'un rendu de gabarit
|
| Une première version rendait les composants à la ficelle, par
| `Blade::render('<x-mail::message>…')`. Elle ne prouvait rien : le résolveur
| de vues mémorise la première résolution d'un nom, si bien qu'un test exécuté
| APRÈS un envoi restait accroché au gabarit du FRAMEWORK — celui qui n'a ni
| preheader ni mode sombre. Le test validait un fichier qui n'était pas le
| nôtre.
|
| On envoie donc pour de bon, par le transport `array`, et on lit le message
| MIME qui en sort. C'est le chemin de production, celui-là même qu'emprunte
| un courriel réel.
|
*/

/** Le message MIME complet d'un courriel réellement expédié, décodé. */
function courrielExpedie(): string
{
    Mail::mailer('array')->to('destinataire@example.test')
        ->send(new OtpCodeMail('123456', OtpPurpose::cases()[0]));

    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    // Le corps part en quoted-printable : sans décodage, une ligne longue est
    // coupée par un « = » et aucune recherche de sous-chaîne ne tient.
    return quoted_printable_decode($messages[0]->getOriginalMessage()->toString());
}

it('rend une version texte brut en plus du HTML', function (): void {
    $corps = courrielExpedie();

    // Un client qui ne rend pas le HTML doit trouver de quoi lire, et le code
    // doit s'y trouver : une version texte qui perdrait l'information utile ne
    // vaudrait pas mieux que pas de version texte du tout.
    expect($corps)->toContain('text/plain')
        ->and($corps)->toContain('text/html')
        ->and($corps)->toContain('123456');
});

it('pose le preheader en TÊTE de corps, et le rend invisible', function (): void {
    $corps = courrielExpedie();

    expect($corps)->toContain('Votre code est valable');

    $positionPreheader = mb_strpos($corps, 'vf-preheader');
    $positionEnveloppe = mb_strpos($corps, 'class="wrapper"');

    // Devant le premier tableau : un preheader placé plus bas laisse le client
    // ramasser le texte qui le précède, et l'aperçu affiche autre chose.
    expect($positionPreheader)->not->toBeFalse()
        ->and($positionEnveloppe)->not->toBeFalse()
        ->and($positionPreheader)->toBeLessThan($positionEnveloppe);

    // Invisible à l'écran, y compris sous Outlook, qui ignore `display`.
    //
    // L'espace après le deux-points n'est pas décoratif dans cette assertion :
    // `CssToInlineStyles` REFORMATE l'attribut `style` en fusionnant les règles
    // du thème, et ressort `mso-hide: all` là où le gabarit écrit
    // `mso-hide:all`. Chercher la forme du gabarit ferait échouer un test sur
    // un courriel parfaitement correct.
    expect($corps)->toContain('mso-hide: all')
        ->and($corps)->toContain('max-height: 0');
});

it('ne met JAMAIS le code de vérification dans l\'aperçu', function (): void {
    $corps = courrielExpedie();

    $debut = (int) mb_strpos($corps, 'vf-preheader');
    $apercu = mb_substr($corps, $debut, 400);

    // L'aperçu s'affiche sur un écran verrouillé, en notification, sans que
    // personne ait eu à déverrouiller le téléphone.
    expect($apercu)->not->toContain('123456');
});

it('déclare un mode sombre qui survit à l\'inlining', function (): void {
    $corps = courrielExpedie();

    expect($corps)->toContain('prefers-color-scheme: dark')
        ->and($corps)->toContain('content="light dark"');

    // Le point qui décide de tout. On isole le bloc sombre et on vérifie que
    // CHACUNE de ses déclarations porte `!important` : sans lui, l'attribut
    // `style` inliné l'emporte et le bloc entier ne sert à rien.
    // Du marqueur jusqu'à la fermeture de la feuille : le bloc sombre est le
    // dernier du `<style>`. Découper sur un nombre de caractères arbitraire
    // ferait déborder l'examen sur du CSS voisin, et le test dirait n'importe
    // quoi le jour où le fichier grandit.
    $debut = (int) mb_strpos($corps, '@media (prefers-color-scheme: dark)');
    $fin = (int) mb_strpos($corps, '</style>', $debut);
    $bloc = mb_substr($corps, $debut, $fin - $debut);

    preg_match_all('/[a-z-]+\s*:\s*[^;{}]+;/i', $bloc, $declarations);

    expect($declarations[0])->not->toBeEmpty();

    foreach ($declarations[0] as $declaration) {
        expect($declaration)->toContain('!important');
    }
});

it('garde la palette de verdict hors du thème, pour qu\'un canal ne la redéfinisse pas', function (): void {
    $theme = mb_strtolower((string) file_get_contents(
        resource_path('views/vendor/mail/html/themes/verifall.css'),
    ));

    // Les teintes de verdict vivent dans `VerdictFactCheck::couleurBarre()`, et
    // nulle part ailleurs : un verdict qui changerait de couleur selon le canal
    // détruirait la reconnaissance qu'on cherche à construire.
    foreach (VerdictFactCheck::cases() as $verdict) {
        expect($theme)->not->toContain(mb_strtolower($verdict->couleurBarre()));
    }
});
