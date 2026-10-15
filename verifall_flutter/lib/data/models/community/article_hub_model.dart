import '../../../domain/entities/article_entity.dart';
import '../json_reader.dart';

/// La lecture d'une brève du Hub.
abstract final class ArticleHubModel {
  /// Les noms lus pour les champs que le contrat ne nomme pas (réserve F.1).
  static const String champChapo = 'chapo';
  static const String champCorps = 'corps';
  static const String champSourcePrimaire = 'source_primaire';
  static const String champPublieLe = 'publie_le';
  static const String champCouverture = 'couverture_url';

  static ArticleHub fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return ArticleHub(
      slug: reader.string('slug'),
      titre: reader.string('titre'),
      // Pour le Hub, `category` est un NOM de categorie (« Politique »,
      // « Sante »), pas un slug : c'est ce que precise le §3.5 du contrat. Il
      // s'affiche donc tel quel.
      categorie: reader.stringOrNull('category'),
      chapo: reader.stringOrNull(champChapo),
      corps: reader.stringOrNull(champCorps),
      // Valeur technique ET libelle voyagent ensemble (§8). On branche sur la
      // premiere, on affiche le second, on n'en fabrique jamais aucun.
      verdict: reader.stringOrNull('verdict'),
      verdictLibelle: reader.stringOrNull('verdict_libelle'),
      sourcePrimaire: reader.stringOrNull(champSourcePrimaire),
      publieLe: reader.dateTimeOrNull(champPublieLe),
      couvertureUrl: reader.stringOrNull(champCouverture),
    );
  }

  static Map<String, Object?> toCacheJson(ArticleHub article) {
    return <String, Object?>{
      'slug': article.slug,
      'titre': article.titre,
      'category': article.categorie,
      champChapo: article.chapo,
      champCorps: article.corps,
      'verdict': article.verdict,
      'verdict_libelle': article.verdictLibelle,
      champSourcePrimaire: article.sourcePrimaire,
      champPublieLe: article.publieLe?.toIso8601String(),
      champCouverture: article.couvertureUrl,
    };
  }
}
