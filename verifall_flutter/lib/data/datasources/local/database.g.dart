// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'database.dart';

// ignore_for_file: type=lint
class CompteCache extends Table with TableInfo<CompteCache, CompteCacheRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  CompteCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _ressourceMeta = const VerificationMeta(
    'ressource',
  );
  late final GeneratedColumn<String> ressource = GeneratedColumn<String>(
    'ressource',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<DateTime> recupereLe = GeneratedColumn<DateTime>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [ressource, chargeUtile, recupereLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'compte_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<CompteCacheRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('ressource')) {
      context.handle(
        _ressourceMeta,
        ressource.isAcceptableOrUnknown(data['ressource']!, _ressourceMeta),
      );
    } else if (isInserting) {
      context.missing(_ressourceMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {ressource};
  @override
  CompteCacheRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CompteCacheRow(
      ressource: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}ressource'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}recupere_le'],
      )!,
    );
  }

  @override
  CompteCache createAlias(String alias) {
    return CompteCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class CompteCacheRow extends DataClass implements Insertable<CompteCacheRow> {
  final String ressource;
  final String chargeUtile;
  final DateTime recupereLe;
  const CompteCacheRow({
    required this.ressource,
    required this.chargeUtile,
    required this.recupereLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['ressource'] = Variable<String>(ressource);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['recupere_le'] = Variable<DateTime>(recupereLe);
    return map;
  }

  CompteCacheCompanion toCompanion(bool nullToAbsent) {
    return CompteCacheCompanion(
      ressource: Value(ressource),
      chargeUtile: Value(chargeUtile),
      recupereLe: Value(recupereLe),
    );
  }

  factory CompteCacheRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CompteCacheRow(
      ressource: serializer.fromJson<String>(json['ressource']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      recupereLe: serializer.fromJson<DateTime>(json['recupere_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'ressource': serializer.toJson<String>(ressource),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'recupere_le': serializer.toJson<DateTime>(recupereLe),
    };
  }

  CompteCacheRow copyWith({
    String? ressource,
    String? chargeUtile,
    DateTime? recupereLe,
  }) => CompteCacheRow(
    ressource: ressource ?? this.ressource,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    recupereLe: recupereLe ?? this.recupereLe,
  );
  CompteCacheRow copyWithCompanion(CompteCacheCompanion data) {
    return CompteCacheRow(
      ressource: data.ressource.present ? data.ressource.value : this.ressource,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CompteCacheRow(')
          ..write('ressource: $ressource, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(ressource, chargeUtile, recupereLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CompteCacheRow &&
          other.ressource == this.ressource &&
          other.chargeUtile == this.chargeUtile &&
          other.recupereLe == this.recupereLe);
}

class CompteCacheCompanion extends UpdateCompanion<CompteCacheRow> {
  final Value<String> ressource;
  final Value<String> chargeUtile;
  final Value<DateTime> recupereLe;
  final Value<int> rowid;
  const CompteCacheCompanion({
    this.ressource = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CompteCacheCompanion.insert({
    required String ressource,
    required String chargeUtile,
    required DateTime recupereLe,
    this.rowid = const Value.absent(),
  }) : ressource = Value(ressource),
       chargeUtile = Value(chargeUtile),
       recupereLe = Value(recupereLe);
  static Insertable<CompteCacheRow> custom({
    Expression<String>? ressource,
    Expression<String>? chargeUtile,
    Expression<DateTime>? recupereLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (ressource != null) 'ressource': ressource,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CompteCacheCompanion copyWith({
    Value<String>? ressource,
    Value<String>? chargeUtile,
    Value<DateTime>? recupereLe,
    Value<int>? rowid,
  }) {
    return CompteCacheCompanion(
      ressource: ressource ?? this.ressource,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      recupereLe: recupereLe ?? this.recupereLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (ressource.present) {
      map['ressource'] = Variable<String>(ressource.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<DateTime>(recupereLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CompteCacheCompanion(')
          ..write('ressource: $ressource, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class CommunityCache extends Table
    with TableInfo<CommunityCache, CommunityCacheRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  CommunityCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cleMeta = const VerificationMeta('cle');
  late final GeneratedColumn<String> cle = GeneratedColumn<String>(
    'cle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<DateTime> recupereLe = GeneratedColumn<DateTime>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [cle, chargeUtile, recupereLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'community_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<CommunityCacheRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cle')) {
      context.handle(
        _cleMeta,
        cle.isAcceptableOrUnknown(data['cle']!, _cleMeta),
      );
    } else if (isInserting) {
      context.missing(_cleMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cle};
  @override
  CommunityCacheRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CommunityCacheRow(
      cle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cle'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}recupere_le'],
      )!,
    );
  }

  @override
  CommunityCache createAlias(String alias) {
    return CommunityCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class CommunityCacheRow extends DataClass
    implements Insertable<CommunityCacheRow> {
  final String cle;
  final String chargeUtile;
  final DateTime recupereLe;
  const CommunityCacheRow({
    required this.cle,
    required this.chargeUtile,
    required this.recupereLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cle'] = Variable<String>(cle);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['recupere_le'] = Variable<DateTime>(recupereLe);
    return map;
  }

  CommunityCacheCompanion toCompanion(bool nullToAbsent) {
    return CommunityCacheCompanion(
      cle: Value(cle),
      chargeUtile: Value(chargeUtile),
      recupereLe: Value(recupereLe),
    );
  }

  factory CommunityCacheRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CommunityCacheRow(
      cle: serializer.fromJson<String>(json['cle']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      recupereLe: serializer.fromJson<DateTime>(json['recupere_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cle': serializer.toJson<String>(cle),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'recupere_le': serializer.toJson<DateTime>(recupereLe),
    };
  }

  CommunityCacheRow copyWith({
    String? cle,
    String? chargeUtile,
    DateTime? recupereLe,
  }) => CommunityCacheRow(
    cle: cle ?? this.cle,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    recupereLe: recupereLe ?? this.recupereLe,
  );
  CommunityCacheRow copyWithCompanion(CommunityCacheCompanion data) {
    return CommunityCacheRow(
      cle: data.cle.present ? data.cle.value : this.cle,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CommunityCacheRow(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cle, chargeUtile, recupereLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CommunityCacheRow &&
          other.cle == this.cle &&
          other.chargeUtile == this.chargeUtile &&
          other.recupereLe == this.recupereLe);
}

class CommunityCacheCompanion extends UpdateCompanion<CommunityCacheRow> {
  final Value<String> cle;
  final Value<String> chargeUtile;
  final Value<DateTime> recupereLe;
  final Value<int> rowid;
  const CommunityCacheCompanion({
    this.cle = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CommunityCacheCompanion.insert({
    required String cle,
    required String chargeUtile,
    required DateTime recupereLe,
    this.rowid = const Value.absent(),
  }) : cle = Value(cle),
       chargeUtile = Value(chargeUtile),
       recupereLe = Value(recupereLe);
  static Insertable<CommunityCacheRow> custom({
    Expression<String>? cle,
    Expression<String>? chargeUtile,
    Expression<DateTime>? recupereLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cle != null) 'cle': cle,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CommunityCacheCompanion copyWith({
    Value<String>? cle,
    Value<String>? chargeUtile,
    Value<DateTime>? recupereLe,
    Value<int>? rowid,
  }) {
    return CommunityCacheCompanion(
      cle: cle ?? this.cle,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      recupereLe: recupereLe ?? this.recupereLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cle.present) {
      map['cle'] = Variable<String>(cle.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<DateTime>(recupereLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CommunityCacheCompanion(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsCatalogue extends Table
    with TableInfo<LmsCatalogue, LmsCatalogueEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsCatalogue(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cleMeta = const VerificationMeta('cle');
  late final GeneratedColumn<String> cle = GeneratedColumn<String>(
    'cle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<int> recupereLe = GeneratedColumn<int>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [cle, chargeUtile, recupereLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_catalogue';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsCatalogueEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cle')) {
      context.handle(
        _cleMeta,
        cle.isAcceptableOrUnknown(data['cle']!, _cleMeta),
      );
    } else if (isInserting) {
      context.missing(_cleMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cle};
  @override
  LmsCatalogueEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsCatalogueEntree(
      cle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cle'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}recupere_le'],
      )!,
    );
  }

  @override
  LmsCatalogue createAlias(String alias) {
    return LmsCatalogue(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsCatalogueEntree extends DataClass
    implements Insertable<LmsCatalogueEntree> {
  final String cle;
  final String chargeUtile;
  final int recupereLe;
  const LmsCatalogueEntree({
    required this.cle,
    required this.chargeUtile,
    required this.recupereLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cle'] = Variable<String>(cle);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['recupere_le'] = Variable<int>(recupereLe);
    return map;
  }

  LmsCatalogueCompanion toCompanion(bool nullToAbsent) {
    return LmsCatalogueCompanion(
      cle: Value(cle),
      chargeUtile: Value(chargeUtile),
      recupereLe: Value(recupereLe),
    );
  }

  factory LmsCatalogueEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsCatalogueEntree(
      cle: serializer.fromJson<String>(json['cle']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      recupereLe: serializer.fromJson<int>(json['recupere_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cle': serializer.toJson<String>(cle),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'recupere_le': serializer.toJson<int>(recupereLe),
    };
  }

  LmsCatalogueEntree copyWith({
    String? cle,
    String? chargeUtile,
    int? recupereLe,
  }) => LmsCatalogueEntree(
    cle: cle ?? this.cle,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    recupereLe: recupereLe ?? this.recupereLe,
  );
  LmsCatalogueEntree copyWithCompanion(LmsCatalogueCompanion data) {
    return LmsCatalogueEntree(
      cle: data.cle.present ? data.cle.value : this.cle,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsCatalogueEntree(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cle, chargeUtile, recupereLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsCatalogueEntree &&
          other.cle == this.cle &&
          other.chargeUtile == this.chargeUtile &&
          other.recupereLe == this.recupereLe);
}

class LmsCatalogueCompanion extends UpdateCompanion<LmsCatalogueEntree> {
  final Value<String> cle;
  final Value<String> chargeUtile;
  final Value<int> recupereLe;
  final Value<int> rowid;
  const LmsCatalogueCompanion({
    this.cle = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsCatalogueCompanion.insert({
    required String cle,
    required String chargeUtile,
    required int recupereLe,
    this.rowid = const Value.absent(),
  }) : cle = Value(cle),
       chargeUtile = Value(chargeUtile),
       recupereLe = Value(recupereLe);
  static Insertable<LmsCatalogueEntree> custom({
    Expression<String>? cle,
    Expression<String>? chargeUtile,
    Expression<int>? recupereLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cle != null) 'cle': cle,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsCatalogueCompanion copyWith({
    Value<String>? cle,
    Value<String>? chargeUtile,
    Value<int>? recupereLe,
    Value<int>? rowid,
  }) {
    return LmsCatalogueCompanion(
      cle: cle ?? this.cle,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      recupereLe: recupereLe ?? this.recupereLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cle.present) {
      map['cle'] = Variable<String>(cle.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<int>(recupereLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsCatalogueCompanion(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsCours extends Table with TableInfo<LmsCours, LmsCoursEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsCours(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _slugMeta = const VerificationMeta('slug');
  late final GeneratedColumn<String> slug = GeneratedColumn<String>(
    'slug',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _metaMeta = const VerificationMeta('meta');
  late final GeneratedColumn<String> meta = GeneratedColumn<String>(
    'meta',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<int> recupereLe = GeneratedColumn<int>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _telechargeLeMeta = const VerificationMeta(
    'telechargeLe',
  );
  late final GeneratedColumn<int> telechargeLe = GeneratedColumn<int>(
    'telecharge_le',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NULL',
  );
  static const VerificationMeta _octetsMeta = const VerificationMeta('octets');
  late final GeneratedColumn<int> octets = GeneratedColumn<int>(
    'octets',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL DEFAULT 0',
    defaultValue: const CustomExpression('0'),
  );
  @override
  List<GeneratedColumn> get $columns => [
    slug,
    chargeUtile,
    meta,
    recupereLe,
    telechargeLe,
    octets,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_cours';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsCoursEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('slug')) {
      context.handle(
        _slugMeta,
        slug.isAcceptableOrUnknown(data['slug']!, _slugMeta),
      );
    } else if (isInserting) {
      context.missing(_slugMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('meta')) {
      context.handle(
        _metaMeta,
        meta.isAcceptableOrUnknown(data['meta']!, _metaMeta),
      );
    } else if (isInserting) {
      context.missing(_metaMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    if (data.containsKey('telecharge_le')) {
      context.handle(
        _telechargeLeMeta,
        telechargeLe.isAcceptableOrUnknown(
          data['telecharge_le']!,
          _telechargeLeMeta,
        ),
      );
    }
    if (data.containsKey('octets')) {
      context.handle(
        _octetsMeta,
        octets.isAcceptableOrUnknown(data['octets']!, _octetsMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {slug};
  @override
  LmsCoursEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsCoursEntree(
      slug: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}slug'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      meta: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}meta'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}recupere_le'],
      )!,
      telechargeLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}telecharge_le'],
      ),
      octets: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}octets'],
      )!,
    );
  }

  @override
  LmsCours createAlias(String alias) {
    return LmsCours(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsCoursEntree extends DataClass implements Insertable<LmsCoursEntree> {
  final String slug;
  final String chargeUtile;
  final String meta;
  final int recupereLe;
  final int? telechargeLe;
  final int octets;
  const LmsCoursEntree({
    required this.slug,
    required this.chargeUtile,
    required this.meta,
    required this.recupereLe,
    this.telechargeLe,
    required this.octets,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['slug'] = Variable<String>(slug);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['meta'] = Variable<String>(meta);
    map['recupere_le'] = Variable<int>(recupereLe);
    if (!nullToAbsent || telechargeLe != null) {
      map['telecharge_le'] = Variable<int>(telechargeLe);
    }
    map['octets'] = Variable<int>(octets);
    return map;
  }

  LmsCoursCompanion toCompanion(bool nullToAbsent) {
    return LmsCoursCompanion(
      slug: Value(slug),
      chargeUtile: Value(chargeUtile),
      meta: Value(meta),
      recupereLe: Value(recupereLe),
      telechargeLe: telechargeLe == null && nullToAbsent
          ? const Value.absent()
          : Value(telechargeLe),
      octets: Value(octets),
    );
  }

  factory LmsCoursEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsCoursEntree(
      slug: serializer.fromJson<String>(json['slug']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      meta: serializer.fromJson<String>(json['meta']),
      recupereLe: serializer.fromJson<int>(json['recupere_le']),
      telechargeLe: serializer.fromJson<int?>(json['telecharge_le']),
      octets: serializer.fromJson<int>(json['octets']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'slug': serializer.toJson<String>(slug),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'meta': serializer.toJson<String>(meta),
      'recupere_le': serializer.toJson<int>(recupereLe),
      'telecharge_le': serializer.toJson<int?>(telechargeLe),
      'octets': serializer.toJson<int>(octets),
    };
  }

  LmsCoursEntree copyWith({
    String? slug,
    String? chargeUtile,
    String? meta,
    int? recupereLe,
    Value<int?> telechargeLe = const Value.absent(),
    int? octets,
  }) => LmsCoursEntree(
    slug: slug ?? this.slug,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    meta: meta ?? this.meta,
    recupereLe: recupereLe ?? this.recupereLe,
    telechargeLe: telechargeLe.present ? telechargeLe.value : this.telechargeLe,
    octets: octets ?? this.octets,
  );
  LmsCoursEntree copyWithCompanion(LmsCoursCompanion data) {
    return LmsCoursEntree(
      slug: data.slug.present ? data.slug.value : this.slug,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      meta: data.meta.present ? data.meta.value : this.meta,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
      telechargeLe: data.telechargeLe.present
          ? data.telechargeLe.value
          : this.telechargeLe,
      octets: data.octets.present ? data.octets.value : this.octets,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsCoursEntree(')
          ..write('slug: $slug, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('meta: $meta, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('telechargeLe: $telechargeLe, ')
          ..write('octets: $octets')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(slug, chargeUtile, meta, recupereLe, telechargeLe, octets);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsCoursEntree &&
          other.slug == this.slug &&
          other.chargeUtile == this.chargeUtile &&
          other.meta == this.meta &&
          other.recupereLe == this.recupereLe &&
          other.telechargeLe == this.telechargeLe &&
          other.octets == this.octets);
}

class LmsCoursCompanion extends UpdateCompanion<LmsCoursEntree> {
  final Value<String> slug;
  final Value<String> chargeUtile;
  final Value<String> meta;
  final Value<int> recupereLe;
  final Value<int?> telechargeLe;
  final Value<int> octets;
  final Value<int> rowid;
  const LmsCoursCompanion({
    this.slug = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.meta = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.telechargeLe = const Value.absent(),
    this.octets = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsCoursCompanion.insert({
    required String slug,
    required String chargeUtile,
    required String meta,
    required int recupereLe,
    this.telechargeLe = const Value.absent(),
    this.octets = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : slug = Value(slug),
       chargeUtile = Value(chargeUtile),
       meta = Value(meta),
       recupereLe = Value(recupereLe);
  static Insertable<LmsCoursEntree> custom({
    Expression<String>? slug,
    Expression<String>? chargeUtile,
    Expression<String>? meta,
    Expression<int>? recupereLe,
    Expression<int>? telechargeLe,
    Expression<int>? octets,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (slug != null) 'slug': slug,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (meta != null) 'meta': meta,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (telechargeLe != null) 'telecharge_le': telechargeLe,
      if (octets != null) 'octets': octets,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsCoursCompanion copyWith({
    Value<String>? slug,
    Value<String>? chargeUtile,
    Value<String>? meta,
    Value<int>? recupereLe,
    Value<int?>? telechargeLe,
    Value<int>? octets,
    Value<int>? rowid,
  }) {
    return LmsCoursCompanion(
      slug: slug ?? this.slug,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      meta: meta ?? this.meta,
      recupereLe: recupereLe ?? this.recupereLe,
      telechargeLe: telechargeLe ?? this.telechargeLe,
      octets: octets ?? this.octets,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (slug.present) {
      map['slug'] = Variable<String>(slug.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (meta.present) {
      map['meta'] = Variable<String>(meta.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<int>(recupereLe.value);
    }
    if (telechargeLe.present) {
      map['telecharge_le'] = Variable<int>(telechargeLe.value);
    }
    if (octets.present) {
      map['octets'] = Variable<int>(octets.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsCoursCompanion(')
          ..write('slug: $slug, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('meta: $meta, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('telechargeLe: $telechargeLe, ')
          ..write('octets: $octets, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsLecon extends Table with TableInfo<LmsLecon, LmsLeconEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsLecon(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _uuidMeta = const VerificationMeta('uuid');
  late final GeneratedColumn<String> uuid = GeneratedColumn<String>(
    'uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _coursSlugMeta = const VerificationMeta(
    'coursSlug',
  );
  late final GeneratedColumn<String> coursSlug = GeneratedColumn<String>(
    'cours_slug',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _secondesRequisesMeta = const VerificationMeta(
    'secondesRequises',
  );
  late final GeneratedColumn<int> secondesRequises = GeneratedColumn<int>(
    'secondes_requises',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<int> recupereLe = GeneratedColumn<int>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _octetsMeta = const VerificationMeta('octets');
  late final GeneratedColumn<int> octets = GeneratedColumn<int>(
    'octets',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL DEFAULT 0',
    defaultValue: const CustomExpression('0'),
  );
  @override
  List<GeneratedColumn> get $columns => [
    uuid,
    coursSlug,
    chargeUtile,
    secondesRequises,
    recupereLe,
    octets,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_lecon';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsLeconEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('uuid')) {
      context.handle(
        _uuidMeta,
        uuid.isAcceptableOrUnknown(data['uuid']!, _uuidMeta),
      );
    } else if (isInserting) {
      context.missing(_uuidMeta);
    }
    if (data.containsKey('cours_slug')) {
      context.handle(
        _coursSlugMeta,
        coursSlug.isAcceptableOrUnknown(data['cours_slug']!, _coursSlugMeta),
      );
    } else if (isInserting) {
      context.missing(_coursSlugMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('secondes_requises')) {
      context.handle(
        _secondesRequisesMeta,
        secondesRequises.isAcceptableOrUnknown(
          data['secondes_requises']!,
          _secondesRequisesMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_secondesRequisesMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    if (data.containsKey('octets')) {
      context.handle(
        _octetsMeta,
        octets.isAcceptableOrUnknown(data['octets']!, _octetsMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {uuid};
  @override
  LmsLeconEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsLeconEntree(
      uuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}uuid'],
      )!,
      coursSlug: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cours_slug'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      secondesRequises: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}secondes_requises'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}recupere_le'],
      )!,
      octets: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}octets'],
      )!,
    );
  }

  @override
  LmsLecon createAlias(String alias) {
    return LmsLecon(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsLeconEntree extends DataClass implements Insertable<LmsLeconEntree> {
  final String uuid;
  final String coursSlug;
  final String chargeUtile;
  final int secondesRequises;
  final int recupereLe;
  final int octets;
  const LmsLeconEntree({
    required this.uuid,
    required this.coursSlug,
    required this.chargeUtile,
    required this.secondesRequises,
    required this.recupereLe,
    required this.octets,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['uuid'] = Variable<String>(uuid);
    map['cours_slug'] = Variable<String>(coursSlug);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['secondes_requises'] = Variable<int>(secondesRequises);
    map['recupere_le'] = Variable<int>(recupereLe);
    map['octets'] = Variable<int>(octets);
    return map;
  }

  LmsLeconCompanion toCompanion(bool nullToAbsent) {
    return LmsLeconCompanion(
      uuid: Value(uuid),
      coursSlug: Value(coursSlug),
      chargeUtile: Value(chargeUtile),
      secondesRequises: Value(secondesRequises),
      recupereLe: Value(recupereLe),
      octets: Value(octets),
    );
  }

  factory LmsLeconEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsLeconEntree(
      uuid: serializer.fromJson<String>(json['uuid']),
      coursSlug: serializer.fromJson<String>(json['cours_slug']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      secondesRequises: serializer.fromJson<int>(json['secondes_requises']),
      recupereLe: serializer.fromJson<int>(json['recupere_le']),
      octets: serializer.fromJson<int>(json['octets']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'uuid': serializer.toJson<String>(uuid),
      'cours_slug': serializer.toJson<String>(coursSlug),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'secondes_requises': serializer.toJson<int>(secondesRequises),
      'recupere_le': serializer.toJson<int>(recupereLe),
      'octets': serializer.toJson<int>(octets),
    };
  }

  LmsLeconEntree copyWith({
    String? uuid,
    String? coursSlug,
    String? chargeUtile,
    int? secondesRequises,
    int? recupereLe,
    int? octets,
  }) => LmsLeconEntree(
    uuid: uuid ?? this.uuid,
    coursSlug: coursSlug ?? this.coursSlug,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    secondesRequises: secondesRequises ?? this.secondesRequises,
    recupereLe: recupereLe ?? this.recupereLe,
    octets: octets ?? this.octets,
  );
  LmsLeconEntree copyWithCompanion(LmsLeconCompanion data) {
    return LmsLeconEntree(
      uuid: data.uuid.present ? data.uuid.value : this.uuid,
      coursSlug: data.coursSlug.present ? data.coursSlug.value : this.coursSlug,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      secondesRequises: data.secondesRequises.present
          ? data.secondesRequises.value
          : this.secondesRequises,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
      octets: data.octets.present ? data.octets.value : this.octets,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsLeconEntree(')
          ..write('uuid: $uuid, ')
          ..write('coursSlug: $coursSlug, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('secondesRequises: $secondesRequises, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('octets: $octets')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    uuid,
    coursSlug,
    chargeUtile,
    secondesRequises,
    recupereLe,
    octets,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsLeconEntree &&
          other.uuid == this.uuid &&
          other.coursSlug == this.coursSlug &&
          other.chargeUtile == this.chargeUtile &&
          other.secondesRequises == this.secondesRequises &&
          other.recupereLe == this.recupereLe &&
          other.octets == this.octets);
}

class LmsLeconCompanion extends UpdateCompanion<LmsLeconEntree> {
  final Value<String> uuid;
  final Value<String> coursSlug;
  final Value<String> chargeUtile;
  final Value<int> secondesRequises;
  final Value<int> recupereLe;
  final Value<int> octets;
  final Value<int> rowid;
  const LmsLeconCompanion({
    this.uuid = const Value.absent(),
    this.coursSlug = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.secondesRequises = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.octets = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsLeconCompanion.insert({
    required String uuid,
    required String coursSlug,
    required String chargeUtile,
    required int secondesRequises,
    required int recupereLe,
    this.octets = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : uuid = Value(uuid),
       coursSlug = Value(coursSlug),
       chargeUtile = Value(chargeUtile),
       secondesRequises = Value(secondesRequises),
       recupereLe = Value(recupereLe);
  static Insertable<LmsLeconEntree> custom({
    Expression<String>? uuid,
    Expression<String>? coursSlug,
    Expression<String>? chargeUtile,
    Expression<int>? secondesRequises,
    Expression<int>? recupereLe,
    Expression<int>? octets,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (uuid != null) 'uuid': uuid,
      if (coursSlug != null) 'cours_slug': coursSlug,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (secondesRequises != null) 'secondes_requises': secondesRequises,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (octets != null) 'octets': octets,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsLeconCompanion copyWith({
    Value<String>? uuid,
    Value<String>? coursSlug,
    Value<String>? chargeUtile,
    Value<int>? secondesRequises,
    Value<int>? recupereLe,
    Value<int>? octets,
    Value<int>? rowid,
  }) {
    return LmsLeconCompanion(
      uuid: uuid ?? this.uuid,
      coursSlug: coursSlug ?? this.coursSlug,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      secondesRequises: secondesRequises ?? this.secondesRequises,
      recupereLe: recupereLe ?? this.recupereLe,
      octets: octets ?? this.octets,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (uuid.present) {
      map['uuid'] = Variable<String>(uuid.value);
    }
    if (coursSlug.present) {
      map['cours_slug'] = Variable<String>(coursSlug.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (secondesRequises.present) {
      map['secondes_requises'] = Variable<int>(secondesRequises.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<int>(recupereLe.value);
    }
    if (octets.present) {
      map['octets'] = Variable<int>(octets.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsLeconCompanion(')
          ..write('uuid: $uuid, ')
          ..write('coursSlug: $coursSlug, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('secondesRequises: $secondesRequises, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('octets: $octets, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsLectureActive extends Table
    with TableInfo<LmsLectureActive, LmsLectureActiveEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsLectureActive(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _leconUuidMeta = const VerificationMeta(
    'leconUuid',
  );
  late final GeneratedColumn<String> leconUuid = GeneratedColumn<String>(
    'lecon_uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _secondesMeta = const VerificationMeta(
    'secondes',
  );
  late final GeneratedColumn<int> secondes = GeneratedColumn<int>(
    'secondes',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL DEFAULT 0',
    defaultValue: const CustomExpression('0'),
  );
  static const VerificationMeta _majLeMeta = const VerificationMeta('majLe');
  late final GeneratedColumn<int> majLe = GeneratedColumn<int>(
    'maj_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [leconUuid, secondes, majLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_lecture_active';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsLectureActiveEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('lecon_uuid')) {
      context.handle(
        _leconUuidMeta,
        leconUuid.isAcceptableOrUnknown(data['lecon_uuid']!, _leconUuidMeta),
      );
    } else if (isInserting) {
      context.missing(_leconUuidMeta);
    }
    if (data.containsKey('secondes')) {
      context.handle(
        _secondesMeta,
        secondes.isAcceptableOrUnknown(data['secondes']!, _secondesMeta),
      );
    }
    if (data.containsKey('maj_le')) {
      context.handle(
        _majLeMeta,
        majLe.isAcceptableOrUnknown(data['maj_le']!, _majLeMeta),
      );
    } else if (isInserting) {
      context.missing(_majLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {leconUuid};
  @override
  LmsLectureActiveEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsLectureActiveEntree(
      leconUuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}lecon_uuid'],
      )!,
      secondes: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}secondes'],
      )!,
      majLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}maj_le'],
      )!,
    );
  }

  @override
  LmsLectureActive createAlias(String alias) {
    return LmsLectureActive(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsLectureActiveEntree extends DataClass
    implements Insertable<LmsLectureActiveEntree> {
  final String leconUuid;
  final int secondes;
  final int majLe;
  const LmsLectureActiveEntree({
    required this.leconUuid,
    required this.secondes,
    required this.majLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['lecon_uuid'] = Variable<String>(leconUuid);
    map['secondes'] = Variable<int>(secondes);
    map['maj_le'] = Variable<int>(majLe);
    return map;
  }

  LmsLectureActiveCompanion toCompanion(bool nullToAbsent) {
    return LmsLectureActiveCompanion(
      leconUuid: Value(leconUuid),
      secondes: Value(secondes),
      majLe: Value(majLe),
    );
  }

  factory LmsLectureActiveEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsLectureActiveEntree(
      leconUuid: serializer.fromJson<String>(json['lecon_uuid']),
      secondes: serializer.fromJson<int>(json['secondes']),
      majLe: serializer.fromJson<int>(json['maj_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'lecon_uuid': serializer.toJson<String>(leconUuid),
      'secondes': serializer.toJson<int>(secondes),
      'maj_le': serializer.toJson<int>(majLe),
    };
  }

  LmsLectureActiveEntree copyWith({
    String? leconUuid,
    int? secondes,
    int? majLe,
  }) => LmsLectureActiveEntree(
    leconUuid: leconUuid ?? this.leconUuid,
    secondes: secondes ?? this.secondes,
    majLe: majLe ?? this.majLe,
  );
  LmsLectureActiveEntree copyWithCompanion(LmsLectureActiveCompanion data) {
    return LmsLectureActiveEntree(
      leconUuid: data.leconUuid.present ? data.leconUuid.value : this.leconUuid,
      secondes: data.secondes.present ? data.secondes.value : this.secondes,
      majLe: data.majLe.present ? data.majLe.value : this.majLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsLectureActiveEntree(')
          ..write('leconUuid: $leconUuid, ')
          ..write('secondes: $secondes, ')
          ..write('majLe: $majLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(leconUuid, secondes, majLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsLectureActiveEntree &&
          other.leconUuid == this.leconUuid &&
          other.secondes == this.secondes &&
          other.majLe == this.majLe);
}

class LmsLectureActiveCompanion
    extends UpdateCompanion<LmsLectureActiveEntree> {
  final Value<String> leconUuid;
  final Value<int> secondes;
  final Value<int> majLe;
  final Value<int> rowid;
  const LmsLectureActiveCompanion({
    this.leconUuid = const Value.absent(),
    this.secondes = const Value.absent(),
    this.majLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsLectureActiveCompanion.insert({
    required String leconUuid,
    this.secondes = const Value.absent(),
    required int majLe,
    this.rowid = const Value.absent(),
  }) : leconUuid = Value(leconUuid),
       majLe = Value(majLe);
  static Insertable<LmsLectureActiveEntree> custom({
    Expression<String>? leconUuid,
    Expression<int>? secondes,
    Expression<int>? majLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (leconUuid != null) 'lecon_uuid': leconUuid,
      if (secondes != null) 'secondes': secondes,
      if (majLe != null) 'maj_le': majLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsLectureActiveCompanion copyWith({
    Value<String>? leconUuid,
    Value<int>? secondes,
    Value<int>? majLe,
    Value<int>? rowid,
  }) {
    return LmsLectureActiveCompanion(
      leconUuid: leconUuid ?? this.leconUuid,
      secondes: secondes ?? this.secondes,
      majLe: majLe ?? this.majLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (leconUuid.present) {
      map['lecon_uuid'] = Variable<String>(leconUuid.value);
    }
    if (secondes.present) {
      map['secondes'] = Variable<int>(secondes.value);
    }
    if (majLe.present) {
      map['maj_le'] = Variable<int>(majLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsLectureActiveCompanion(')
          ..write('leconUuid: $leconUuid, ')
          ..write('secondes: $secondes, ')
          ..write('majLe: $majLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsCertificat extends Table
    with TableInfo<LmsCertificat, LmsCertificatEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsCertificat(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _codeMeta = const VerificationMeta('code');
  late final GeneratedColumn<String> code = GeneratedColumn<String>(
    'code',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<int> recupereLe = GeneratedColumn<int>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [code, chargeUtile, recupereLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_certificat';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsCertificatEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('code')) {
      context.handle(
        _codeMeta,
        code.isAcceptableOrUnknown(data['code']!, _codeMeta),
      );
    } else if (isInserting) {
      context.missing(_codeMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {code};
  @override
  LmsCertificatEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsCertificatEntree(
      code: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}code'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}recupere_le'],
      )!,
    );
  }

  @override
  LmsCertificat createAlias(String alias) {
    return LmsCertificat(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsCertificatEntree extends DataClass
    implements Insertable<LmsCertificatEntree> {
  final String code;
  final String chargeUtile;
  final int recupereLe;
  const LmsCertificatEntree({
    required this.code,
    required this.chargeUtile,
    required this.recupereLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['code'] = Variable<String>(code);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['recupere_le'] = Variable<int>(recupereLe);
    return map;
  }

  LmsCertificatCompanion toCompanion(bool nullToAbsent) {
    return LmsCertificatCompanion(
      code: Value(code),
      chargeUtile: Value(chargeUtile),
      recupereLe: Value(recupereLe),
    );
  }

  factory LmsCertificatEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsCertificatEntree(
      code: serializer.fromJson<String>(json['code']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      recupereLe: serializer.fromJson<int>(json['recupere_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'code': serializer.toJson<String>(code),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'recupere_le': serializer.toJson<int>(recupereLe),
    };
  }

  LmsCertificatEntree copyWith({
    String? code,
    String? chargeUtile,
    int? recupereLe,
  }) => LmsCertificatEntree(
    code: code ?? this.code,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    recupereLe: recupereLe ?? this.recupereLe,
  );
  LmsCertificatEntree copyWithCompanion(LmsCertificatCompanion data) {
    return LmsCertificatEntree(
      code: data.code.present ? data.code.value : this.code,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsCertificatEntree(')
          ..write('code: $code, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(code, chargeUtile, recupereLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsCertificatEntree &&
          other.code == this.code &&
          other.chargeUtile == this.chargeUtile &&
          other.recupereLe == this.recupereLe);
}

class LmsCertificatCompanion extends UpdateCompanion<LmsCertificatEntree> {
  final Value<String> code;
  final Value<String> chargeUtile;
  final Value<int> recupereLe;
  final Value<int> rowid;
  const LmsCertificatCompanion({
    this.code = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsCertificatCompanion.insert({
    required String code,
    required String chargeUtile,
    required int recupereLe,
    this.rowid = const Value.absent(),
  }) : code = Value(code),
       chargeUtile = Value(chargeUtile),
       recupereLe = Value(recupereLe);
  static Insertable<LmsCertificatEntree> custom({
    Expression<String>? code,
    Expression<String>? chargeUtile,
    Expression<int>? recupereLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (code != null) 'code': code,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsCertificatCompanion copyWith({
    Value<String>? code,
    Value<String>? chargeUtile,
    Value<int>? recupereLe,
    Value<int>? rowid,
  }) {
    return LmsCertificatCompanion(
      code: code ?? this.code,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      recupereLe: recupereLe ?? this.recupereLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (code.present) {
      map['code'] = Variable<String>(code.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<int>(recupereLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsCertificatCompanion(')
          ..write('code: $code, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class LmsSeances extends Table with TableInfo<LmsSeances, LmsSeancesEntree> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  LmsSeances(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cleMeta = const VerificationMeta('cle');
  late final GeneratedColumn<String> cle = GeneratedColumn<String>(
    'cle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeUtileMeta = const VerificationMeta(
    'chargeUtile',
  );
  late final GeneratedColumn<String> chargeUtile = GeneratedColumn<String>(
    'charge_utile',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _recupereLeMeta = const VerificationMeta(
    'recupereLe',
  );
  late final GeneratedColumn<int> recupereLe = GeneratedColumn<int>(
    'recupere_le',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [cle, chargeUtile, recupereLe];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'lms_seances';
  @override
  VerificationContext validateIntegrity(
    Insertable<LmsSeancesEntree> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cle')) {
      context.handle(
        _cleMeta,
        cle.isAcceptableOrUnknown(data['cle']!, _cleMeta),
      );
    } else if (isInserting) {
      context.missing(_cleMeta);
    }
    if (data.containsKey('charge_utile')) {
      context.handle(
        _chargeUtileMeta,
        chargeUtile.isAcceptableOrUnknown(
          data['charge_utile']!,
          _chargeUtileMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_chargeUtileMeta);
    }
    if (data.containsKey('recupere_le')) {
      context.handle(
        _recupereLeMeta,
        recupereLe.isAcceptableOrUnknown(data['recupere_le']!, _recupereLeMeta),
      );
    } else if (isInserting) {
      context.missing(_recupereLeMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cle};
  @override
  LmsSeancesEntree map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LmsSeancesEntree(
      cle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cle'],
      )!,
      chargeUtile: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_utile'],
      )!,
      recupereLe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}recupere_le'],
      )!,
    );
  }

  @override
  LmsSeances createAlias(String alias) {
    return LmsSeances(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class LmsSeancesEntree extends DataClass
    implements Insertable<LmsSeancesEntree> {
  final String cle;
  final String chargeUtile;
  final int recupereLe;
  const LmsSeancesEntree({
    required this.cle,
    required this.chargeUtile,
    required this.recupereLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cle'] = Variable<String>(cle);
    map['charge_utile'] = Variable<String>(chargeUtile);
    map['recupere_le'] = Variable<int>(recupereLe);
    return map;
  }

  LmsSeancesCompanion toCompanion(bool nullToAbsent) {
    return LmsSeancesCompanion(
      cle: Value(cle),
      chargeUtile: Value(chargeUtile),
      recupereLe: Value(recupereLe),
    );
  }

  factory LmsSeancesEntree.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LmsSeancesEntree(
      cle: serializer.fromJson<String>(json['cle']),
      chargeUtile: serializer.fromJson<String>(json['charge_utile']),
      recupereLe: serializer.fromJson<int>(json['recupere_le']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cle': serializer.toJson<String>(cle),
      'charge_utile': serializer.toJson<String>(chargeUtile),
      'recupere_le': serializer.toJson<int>(recupereLe),
    };
  }

  LmsSeancesEntree copyWith({
    String? cle,
    String? chargeUtile,
    int? recupereLe,
  }) => LmsSeancesEntree(
    cle: cle ?? this.cle,
    chargeUtile: chargeUtile ?? this.chargeUtile,
    recupereLe: recupereLe ?? this.recupereLe,
  );
  LmsSeancesEntree copyWithCompanion(LmsSeancesCompanion data) {
    return LmsSeancesEntree(
      cle: data.cle.present ? data.cle.value : this.cle,
      chargeUtile: data.chargeUtile.present
          ? data.chargeUtile.value
          : this.chargeUtile,
      recupereLe: data.recupereLe.present
          ? data.recupereLe.value
          : this.recupereLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LmsSeancesEntree(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cle, chargeUtile, recupereLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LmsSeancesEntree &&
          other.cle == this.cle &&
          other.chargeUtile == this.chargeUtile &&
          other.recupereLe == this.recupereLe);
}

class LmsSeancesCompanion extends UpdateCompanion<LmsSeancesEntree> {
  final Value<String> cle;
  final Value<String> chargeUtile;
  final Value<int> recupereLe;
  final Value<int> rowid;
  const LmsSeancesCompanion({
    this.cle = const Value.absent(),
    this.chargeUtile = const Value.absent(),
    this.recupereLe = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LmsSeancesCompanion.insert({
    required String cle,
    required String chargeUtile,
    required int recupereLe,
    this.rowid = const Value.absent(),
  }) : cle = Value(cle),
       chargeUtile = Value(chargeUtile),
       recupereLe = Value(recupereLe);
  static Insertable<LmsSeancesEntree> custom({
    Expression<String>? cle,
    Expression<String>? chargeUtile,
    Expression<int>? recupereLe,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cle != null) 'cle': cle,
      if (chargeUtile != null) 'charge_utile': chargeUtile,
      if (recupereLe != null) 'recupere_le': recupereLe,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LmsSeancesCompanion copyWith({
    Value<String>? cle,
    Value<String>? chargeUtile,
    Value<int>? recupereLe,
    Value<int>? rowid,
  }) {
    return LmsSeancesCompanion(
      cle: cle ?? this.cle,
      chargeUtile: chargeUtile ?? this.chargeUtile,
      recupereLe: recupereLe ?? this.recupereLe,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cle.present) {
      map['cle'] = Variable<String>(cle.value);
    }
    if (chargeUtile.present) {
      map['charge_utile'] = Variable<String>(chargeUtile.value);
    }
    if (recupereLe.present) {
      map['recupere_le'] = Variable<int>(recupereLe.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LmsSeancesCompanion(')
          ..write('cle: $cle, ')
          ..write('chargeUtile: $chargeUtile, ')
          ..write('recupereLe: $recupereLe, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class VerificationCache extends Table
    with TableInfo<VerificationCache, VerificationCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  VerificationCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _uuidMeta = const VerificationMeta('uuid');
  late final GeneratedColumn<String> uuid = GeneratedColumn<String>(
    'uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeJsonMeta = const VerificationMeta(
    'chargeJson',
  );
  late final GeneratedColumn<String> chargeJson = GeneratedColumn<String>(
    'charge_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _termineeMeta = const VerificationMeta(
    'terminee',
  );
  late final GeneratedColumn<bool> terminee = GeneratedColumn<bool>(
    'terminee',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _rangMeta = const VerificationMeta('rang');
  late final GeneratedColumn<int> rang = GeneratedColumn<int>(
    'rang',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _soumiseLeMeta = const VerificationMeta(
    'soumiseLe',
  );
  late final GeneratedColumn<DateTime> soumiseLe = GeneratedColumn<DateTime>(
    'soumise_le',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    $customConstraints: '',
  );
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta(
    'fetchedAt',
  );
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [
    uuid,
    chargeJson,
    terminee,
    rang,
    soumiseLe,
    fetchedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'verification_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<VerificationCacheData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('uuid')) {
      context.handle(
        _uuidMeta,
        uuid.isAcceptableOrUnknown(data['uuid']!, _uuidMeta),
      );
    } else if (isInserting) {
      context.missing(_uuidMeta);
    }
    if (data.containsKey('charge_json')) {
      context.handle(
        _chargeJsonMeta,
        chargeJson.isAcceptableOrUnknown(data['charge_json']!, _chargeJsonMeta),
      );
    } else if (isInserting) {
      context.missing(_chargeJsonMeta);
    }
    if (data.containsKey('terminee')) {
      context.handle(
        _termineeMeta,
        terminee.isAcceptableOrUnknown(data['terminee']!, _termineeMeta),
      );
    } else if (isInserting) {
      context.missing(_termineeMeta);
    }
    if (data.containsKey('rang')) {
      context.handle(
        _rangMeta,
        rang.isAcceptableOrUnknown(data['rang']!, _rangMeta),
      );
    } else if (isInserting) {
      context.missing(_rangMeta);
    }
    if (data.containsKey('soumise_le')) {
      context.handle(
        _soumiseLeMeta,
        soumiseLe.isAcceptableOrUnknown(data['soumise_le']!, _soumiseLeMeta),
      );
    }
    if (data.containsKey('fetched_at')) {
      context.handle(
        _fetchedAtMeta,
        fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {uuid};
  @override
  VerificationCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return VerificationCacheData(
      uuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}uuid'],
      )!,
      chargeJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_json'],
      )!,
      terminee: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}terminee'],
      )!,
      rang: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}rang'],
      )!,
      soumiseLe: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}soumise_le'],
      ),
      fetchedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}fetched_at'],
      )!,
    );
  }

  @override
  VerificationCache createAlias(String alias) {
    return VerificationCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class VerificationCacheData extends DataClass
    implements Insertable<VerificationCacheData> {
  final String uuid;
  final String chargeJson;
  final bool terminee;
  final int rang;
  final DateTime? soumiseLe;
  final DateTime fetchedAt;
  const VerificationCacheData({
    required this.uuid,
    required this.chargeJson,
    required this.terminee,
    required this.rang,
    this.soumiseLe,
    required this.fetchedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['uuid'] = Variable<String>(uuid);
    map['charge_json'] = Variable<String>(chargeJson);
    map['terminee'] = Variable<bool>(terminee);
    map['rang'] = Variable<int>(rang);
    if (!nullToAbsent || soumiseLe != null) {
      map['soumise_le'] = Variable<DateTime>(soumiseLe);
    }
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  VerificationCacheCompanion toCompanion(bool nullToAbsent) {
    return VerificationCacheCompanion(
      uuid: Value(uuid),
      chargeJson: Value(chargeJson),
      terminee: Value(terminee),
      rang: Value(rang),
      soumiseLe: soumiseLe == null && nullToAbsent
          ? const Value.absent()
          : Value(soumiseLe),
      fetchedAt: Value(fetchedAt),
    );
  }

  factory VerificationCacheData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return VerificationCacheData(
      uuid: serializer.fromJson<String>(json['uuid']),
      chargeJson: serializer.fromJson<String>(json['charge_json']),
      terminee: serializer.fromJson<bool>(json['terminee']),
      rang: serializer.fromJson<int>(json['rang']),
      soumiseLe: serializer.fromJson<DateTime?>(json['soumise_le']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetched_at']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'uuid': serializer.toJson<String>(uuid),
      'charge_json': serializer.toJson<String>(chargeJson),
      'terminee': serializer.toJson<bool>(terminee),
      'rang': serializer.toJson<int>(rang),
      'soumise_le': serializer.toJson<DateTime?>(soumiseLe),
      'fetched_at': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  VerificationCacheData copyWith({
    String? uuid,
    String? chargeJson,
    bool? terminee,
    int? rang,
    Value<DateTime?> soumiseLe = const Value.absent(),
    DateTime? fetchedAt,
  }) => VerificationCacheData(
    uuid: uuid ?? this.uuid,
    chargeJson: chargeJson ?? this.chargeJson,
    terminee: terminee ?? this.terminee,
    rang: rang ?? this.rang,
    soumiseLe: soumiseLe.present ? soumiseLe.value : this.soumiseLe,
    fetchedAt: fetchedAt ?? this.fetchedAt,
  );
  VerificationCacheData copyWithCompanion(VerificationCacheCompanion data) {
    return VerificationCacheData(
      uuid: data.uuid.present ? data.uuid.value : this.uuid,
      chargeJson: data.chargeJson.present
          ? data.chargeJson.value
          : this.chargeJson,
      terminee: data.terminee.present ? data.terminee.value : this.terminee,
      rang: data.rang.present ? data.rang.value : this.rang,
      soumiseLe: data.soumiseLe.present ? data.soumiseLe.value : this.soumiseLe,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('VerificationCacheData(')
          ..write('uuid: $uuid, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('terminee: $terminee, ')
          ..write('rang: $rang, ')
          ..write('soumiseLe: $soumiseLe, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(uuid, chargeJson, terminee, rang, soumiseLe, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is VerificationCacheData &&
          other.uuid == this.uuid &&
          other.chargeJson == this.chargeJson &&
          other.terminee == this.terminee &&
          other.rang == this.rang &&
          other.soumiseLe == this.soumiseLe &&
          other.fetchedAt == this.fetchedAt);
}

class VerificationCacheCompanion
    extends UpdateCompanion<VerificationCacheData> {
  final Value<String> uuid;
  final Value<String> chargeJson;
  final Value<bool> terminee;
  final Value<int> rang;
  final Value<DateTime?> soumiseLe;
  final Value<DateTime> fetchedAt;
  final Value<int> rowid;
  const VerificationCacheCompanion({
    this.uuid = const Value.absent(),
    this.chargeJson = const Value.absent(),
    this.terminee = const Value.absent(),
    this.rang = const Value.absent(),
    this.soumiseLe = const Value.absent(),
    this.fetchedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  VerificationCacheCompanion.insert({
    required String uuid,
    required String chargeJson,
    required bool terminee,
    required int rang,
    this.soumiseLe = const Value.absent(),
    required DateTime fetchedAt,
    this.rowid = const Value.absent(),
  }) : uuid = Value(uuid),
       chargeJson = Value(chargeJson),
       terminee = Value(terminee),
       rang = Value(rang),
       fetchedAt = Value(fetchedAt);
  static Insertable<VerificationCacheData> custom({
    Expression<String>? uuid,
    Expression<String>? chargeJson,
    Expression<bool>? terminee,
    Expression<int>? rang,
    Expression<DateTime>? soumiseLe,
    Expression<DateTime>? fetchedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (uuid != null) 'uuid': uuid,
      if (chargeJson != null) 'charge_json': chargeJson,
      if (terminee != null) 'terminee': terminee,
      if (rang != null) 'rang': rang,
      if (soumiseLe != null) 'soumise_le': soumiseLe,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  VerificationCacheCompanion copyWith({
    Value<String>? uuid,
    Value<String>? chargeJson,
    Value<bool>? terminee,
    Value<int>? rang,
    Value<DateTime?>? soumiseLe,
    Value<DateTime>? fetchedAt,
    Value<int>? rowid,
  }) {
    return VerificationCacheCompanion(
      uuid: uuid ?? this.uuid,
      chargeJson: chargeJson ?? this.chargeJson,
      terminee: terminee ?? this.terminee,
      rang: rang ?? this.rang,
      soumiseLe: soumiseLe ?? this.soumiseLe,
      fetchedAt: fetchedAt ?? this.fetchedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (uuid.present) {
      map['uuid'] = Variable<String>(uuid.value);
    }
    if (chargeJson.present) {
      map['charge_json'] = Variable<String>(chargeJson.value);
    }
    if (terminee.present) {
      map['terminee'] = Variable<bool>(terminee.value);
    }
    if (rang.present) {
      map['rang'] = Variable<int>(rang.value);
    }
    if (soumiseLe.present) {
      map['soumise_le'] = Variable<DateTime>(soumiseLe.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('VerificationCacheCompanion(')
          ..write('uuid: $uuid, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('terminee: $terminee, ')
          ..write('rang: $rang, ')
          ..write('soumiseLe: $soumiseLe, ')
          ..write('fetchedAt: $fetchedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class AnnuaireOutilsCache extends Table
    with TableInfo<AnnuaireOutilsCache, AnnuaireOutilsCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  AnnuaireOutilsCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeJsonMeta = const VerificationMeta(
    'chargeJson',
  );
  late final GeneratedColumn<String> chargeJson = GeneratedColumn<String>(
    'charge_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta(
    'fetchedAt',
  );
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [id, chargeJson, fetchedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'annuaire_outils_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<AnnuaireOutilsCacheData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('charge_json')) {
      context.handle(
        _chargeJsonMeta,
        chargeJson.isAcceptableOrUnknown(data['charge_json']!, _chargeJsonMeta),
      );
    } else if (isInserting) {
      context.missing(_chargeJsonMeta);
    }
    if (data.containsKey('fetched_at')) {
      context.handle(
        _fetchedAtMeta,
        fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  AnnuaireOutilsCacheData map(
    Map<String, dynamic> data, {
    String? tablePrefix,
  }) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return AnnuaireOutilsCacheData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      chargeJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_json'],
      )!,
      fetchedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}fetched_at'],
      )!,
    );
  }

  @override
  AnnuaireOutilsCache createAlias(String alias) {
    return AnnuaireOutilsCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class AnnuaireOutilsCacheData extends DataClass
    implements Insertable<AnnuaireOutilsCacheData> {
  final int id;

  /// toujours 0 : une seule ligne
  final String chargeJson;

  /// { outils: [...], categories: [...] }
  final DateTime fetchedAt;
  const AnnuaireOutilsCacheData({
    required this.id,
    required this.chargeJson,
    required this.fetchedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['charge_json'] = Variable<String>(chargeJson);
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  AnnuaireOutilsCacheCompanion toCompanion(bool nullToAbsent) {
    return AnnuaireOutilsCacheCompanion(
      id: Value(id),
      chargeJson: Value(chargeJson),
      fetchedAt: Value(fetchedAt),
    );
  }

  factory AnnuaireOutilsCacheData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return AnnuaireOutilsCacheData(
      id: serializer.fromJson<int>(json['id']),
      chargeJson: serializer.fromJson<String>(json['charge_json']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetched_at']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'charge_json': serializer.toJson<String>(chargeJson),
      'fetched_at': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  AnnuaireOutilsCacheData copyWith({
    int? id,
    String? chargeJson,
    DateTime? fetchedAt,
  }) => AnnuaireOutilsCacheData(
    id: id ?? this.id,
    chargeJson: chargeJson ?? this.chargeJson,
    fetchedAt: fetchedAt ?? this.fetchedAt,
  );
  AnnuaireOutilsCacheData copyWithCompanion(AnnuaireOutilsCacheCompanion data) {
    return AnnuaireOutilsCacheData(
      id: data.id.present ? data.id.value : this.id,
      chargeJson: data.chargeJson.present
          ? data.chargeJson.value
          : this.chargeJson,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('AnnuaireOutilsCacheData(')
          ..write('id: $id, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, chargeJson, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is AnnuaireOutilsCacheData &&
          other.id == this.id &&
          other.chargeJson == this.chargeJson &&
          other.fetchedAt == this.fetchedAt);
}

class AnnuaireOutilsCacheCompanion
    extends UpdateCompanion<AnnuaireOutilsCacheData> {
  final Value<int> id;
  final Value<String> chargeJson;
  final Value<DateTime> fetchedAt;
  const AnnuaireOutilsCacheCompanion({
    this.id = const Value.absent(),
    this.chargeJson = const Value.absent(),
    this.fetchedAt = const Value.absent(),
  });
  AnnuaireOutilsCacheCompanion.insert({
    this.id = const Value.absent(),
    required String chargeJson,
    required DateTime fetchedAt,
  }) : chargeJson = Value(chargeJson),
       fetchedAt = Value(fetchedAt);
  static Insertable<AnnuaireOutilsCacheData> custom({
    Expression<int>? id,
    Expression<String>? chargeJson,
    Expression<DateTime>? fetchedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (chargeJson != null) 'charge_json': chargeJson,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
    });
  }

  AnnuaireOutilsCacheCompanion copyWith({
    Value<int>? id,
    Value<String>? chargeJson,
    Value<DateTime>? fetchedAt,
  }) {
    return AnnuaireOutilsCacheCompanion(
      id: id ?? this.id,
      chargeJson: chargeJson ?? this.chargeJson,
      fetchedAt: fetchedAt ?? this.fetchedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (chargeJson.present) {
      map['charge_json'] = Variable<String>(chargeJson.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('AnnuaireOutilsCacheCompanion(')
          ..write('id: $id, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }
}

class TableauDeBordCache extends Table
    with TableInfo<TableauDeBordCache, TableauDeBordCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  TableauDeBordCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _chargeJsonMeta = const VerificationMeta(
    'chargeJson',
  );
  late final GeneratedColumn<String> chargeJson = GeneratedColumn<String>(
    'charge_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta(
    'fetchedAt',
  );
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [id, chargeJson, fetchedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'tableau_de_bord_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<TableauDeBordCacheData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('charge_json')) {
      context.handle(
        _chargeJsonMeta,
        chargeJson.isAcceptableOrUnknown(data['charge_json']!, _chargeJsonMeta),
      );
    } else if (isInserting) {
      context.missing(_chargeJsonMeta);
    }
    if (data.containsKey('fetched_at')) {
      context.handle(
        _fetchedAtMeta,
        fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  TableauDeBordCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return TableauDeBordCacheData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      chargeJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_json'],
      )!,
      fetchedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}fetched_at'],
      )!,
    );
  }

  @override
  TableauDeBordCache createAlias(String alias) {
    return TableauDeBordCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class TableauDeBordCacheData extends DataClass
    implements Insertable<TableauDeBordCacheData> {
  final int id;

  /// toujours 0 : une seule ligne
  final String chargeJson;

  /// l'agregat, SANS le solde
  final DateTime fetchedAt;
  const TableauDeBordCacheData({
    required this.id,
    required this.chargeJson,
    required this.fetchedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['charge_json'] = Variable<String>(chargeJson);
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  TableauDeBordCacheCompanion toCompanion(bool nullToAbsent) {
    return TableauDeBordCacheCompanion(
      id: Value(id),
      chargeJson: Value(chargeJson),
      fetchedAt: Value(fetchedAt),
    );
  }

  factory TableauDeBordCacheData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return TableauDeBordCacheData(
      id: serializer.fromJson<int>(json['id']),
      chargeJson: serializer.fromJson<String>(json['charge_json']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetched_at']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'charge_json': serializer.toJson<String>(chargeJson),
      'fetched_at': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  TableauDeBordCacheData copyWith({
    int? id,
    String? chargeJson,
    DateTime? fetchedAt,
  }) => TableauDeBordCacheData(
    id: id ?? this.id,
    chargeJson: chargeJson ?? this.chargeJson,
    fetchedAt: fetchedAt ?? this.fetchedAt,
  );
  TableauDeBordCacheData copyWithCompanion(TableauDeBordCacheCompanion data) {
    return TableauDeBordCacheData(
      id: data.id.present ? data.id.value : this.id,
      chargeJson: data.chargeJson.present
          ? data.chargeJson.value
          : this.chargeJson,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('TableauDeBordCacheData(')
          ..write('id: $id, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, chargeJson, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is TableauDeBordCacheData &&
          other.id == this.id &&
          other.chargeJson == this.chargeJson &&
          other.fetchedAt == this.fetchedAt);
}

class TableauDeBordCacheCompanion
    extends UpdateCompanion<TableauDeBordCacheData> {
  final Value<int> id;
  final Value<String> chargeJson;
  final Value<DateTime> fetchedAt;
  const TableauDeBordCacheCompanion({
    this.id = const Value.absent(),
    this.chargeJson = const Value.absent(),
    this.fetchedAt = const Value.absent(),
  });
  TableauDeBordCacheCompanion.insert({
    this.id = const Value.absent(),
    required String chargeJson,
    required DateTime fetchedAt,
  }) : chargeJson = Value(chargeJson),
       fetchedAt = Value(fetchedAt);
  static Insertable<TableauDeBordCacheData> custom({
    Expression<int>? id,
    Expression<String>? chargeJson,
    Expression<DateTime>? fetchedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (chargeJson != null) 'charge_json': chargeJson,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
    });
  }

  TableauDeBordCacheCompanion copyWith({
    Value<int>? id,
    Value<String>? chargeJson,
    Value<DateTime>? fetchedAt,
  }) {
    return TableauDeBordCacheCompanion(
      id: id ?? this.id,
      chargeJson: chargeJson ?? this.chargeJson,
      fetchedAt: fetchedAt ?? this.fetchedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (chargeJson.present) {
      map['charge_json'] = Variable<String>(chargeJson.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TableauDeBordCacheCompanion(')
          ..write('id: $id, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }
}

class RepriseDeCoursCache extends Table
    with TableInfo<RepriseDeCoursCache, RepriseDeCoursCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  RepriseDeCoursCache(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    $customConstraints: 'NOT NULL PRIMARY KEY',
  );
  static const VerificationMeta _presenteMeta = const VerificationMeta(
    'presente',
  );
  late final GeneratedColumn<bool> presente = GeneratedColumn<bool>(
    'presente',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  static const VerificationMeta _chargeJsonMeta = const VerificationMeta(
    'chargeJson',
  );
  late final GeneratedColumn<String> chargeJson = GeneratedColumn<String>(
    'charge_json',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    $customConstraints: '',
  );
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta(
    'fetchedAt',
  );
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
    $customConstraints: 'NOT NULL',
  );
  @override
  List<GeneratedColumn> get $columns => [id, presente, chargeJson, fetchedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'reprise_de_cours_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<RepriseDeCoursCacheData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('presente')) {
      context.handle(
        _presenteMeta,
        presente.isAcceptableOrUnknown(data['presente']!, _presenteMeta),
      );
    } else if (isInserting) {
      context.missing(_presenteMeta);
    }
    if (data.containsKey('charge_json')) {
      context.handle(
        _chargeJsonMeta,
        chargeJson.isAcceptableOrUnknown(data['charge_json']!, _chargeJsonMeta),
      );
    }
    if (data.containsKey('fetched_at')) {
      context.handle(
        _fetchedAtMeta,
        fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  RepriseDeCoursCacheData map(
    Map<String, dynamic> data, {
    String? tablePrefix,
  }) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return RepriseDeCoursCacheData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      presente: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}presente'],
      )!,
      chargeJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}charge_json'],
      ),
      fetchedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}fetched_at'],
      )!,
    );
  }

  @override
  RepriseDeCoursCache createAlias(String alias) {
    return RepriseDeCoursCache(attachedDatabase, alias);
  }

  @override
  bool get dontWriteConstraints => true;
}

class RepriseDeCoursCacheData extends DataClass
    implements Insertable<RepriseDeCoursCacheData> {
  final int id;

  /// toujours 0 : une seule ligne
  final bool presente;
  final String? chargeJson;
  final DateTime fetchedAt;
  const RepriseDeCoursCacheData({
    required this.id,
    required this.presente,
    this.chargeJson,
    required this.fetchedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['presente'] = Variable<bool>(presente);
    if (!nullToAbsent || chargeJson != null) {
      map['charge_json'] = Variable<String>(chargeJson);
    }
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  RepriseDeCoursCacheCompanion toCompanion(bool nullToAbsent) {
    return RepriseDeCoursCacheCompanion(
      id: Value(id),
      presente: Value(presente),
      chargeJson: chargeJson == null && nullToAbsent
          ? const Value.absent()
          : Value(chargeJson),
      fetchedAt: Value(fetchedAt),
    );
  }

  factory RepriseDeCoursCacheData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return RepriseDeCoursCacheData(
      id: serializer.fromJson<int>(json['id']),
      presente: serializer.fromJson<bool>(json['presente']),
      chargeJson: serializer.fromJson<String?>(json['charge_json']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetched_at']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'presente': serializer.toJson<bool>(presente),
      'charge_json': serializer.toJson<String?>(chargeJson),
      'fetched_at': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  RepriseDeCoursCacheData copyWith({
    int? id,
    bool? presente,
    Value<String?> chargeJson = const Value.absent(),
    DateTime? fetchedAt,
  }) => RepriseDeCoursCacheData(
    id: id ?? this.id,
    presente: presente ?? this.presente,
    chargeJson: chargeJson.present ? chargeJson.value : this.chargeJson,
    fetchedAt: fetchedAt ?? this.fetchedAt,
  );
  RepriseDeCoursCacheData copyWithCompanion(RepriseDeCoursCacheCompanion data) {
    return RepriseDeCoursCacheData(
      id: data.id.present ? data.id.value : this.id,
      presente: data.presente.present ? data.presente.value : this.presente,
      chargeJson: data.chargeJson.present
          ? data.chargeJson.value
          : this.chargeJson,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('RepriseDeCoursCacheData(')
          ..write('id: $id, ')
          ..write('presente: $presente, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, presente, chargeJson, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is RepriseDeCoursCacheData &&
          other.id == this.id &&
          other.presente == this.presente &&
          other.chargeJson == this.chargeJson &&
          other.fetchedAt == this.fetchedAt);
}

class RepriseDeCoursCacheCompanion
    extends UpdateCompanion<RepriseDeCoursCacheData> {
  final Value<int> id;
  final Value<bool> presente;
  final Value<String?> chargeJson;
  final Value<DateTime> fetchedAt;
  const RepriseDeCoursCacheCompanion({
    this.id = const Value.absent(),
    this.presente = const Value.absent(),
    this.chargeJson = const Value.absent(),
    this.fetchedAt = const Value.absent(),
  });
  RepriseDeCoursCacheCompanion.insert({
    this.id = const Value.absent(),
    required bool presente,
    this.chargeJson = const Value.absent(),
    required DateTime fetchedAt,
  }) : presente = Value(presente),
       fetchedAt = Value(fetchedAt);
  static Insertable<RepriseDeCoursCacheData> custom({
    Expression<int>? id,
    Expression<bool>? presente,
    Expression<String>? chargeJson,
    Expression<DateTime>? fetchedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (presente != null) 'presente': presente,
      if (chargeJson != null) 'charge_json': chargeJson,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
    });
  }

  RepriseDeCoursCacheCompanion copyWith({
    Value<int>? id,
    Value<bool>? presente,
    Value<String?>? chargeJson,
    Value<DateTime>? fetchedAt,
  }) {
    return RepriseDeCoursCacheCompanion(
      id: id ?? this.id,
      presente: presente ?? this.presente,
      chargeJson: chargeJson ?? this.chargeJson,
      fetchedAt: fetchedAt ?? this.fetchedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (presente.present) {
      map['presente'] = Variable<bool>(presente.value);
    }
    if (chargeJson.present) {
      map['charge_json'] = Variable<String>(chargeJson.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('RepriseDeCoursCacheCompanion(')
          ..write('id: $id, ')
          ..write('presente: $presente, ')
          ..write('chargeJson: $chargeJson, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }
}

class $PendingOpsTable extends PendingOps
    with TableInfo<$PendingOpsTable, PendingOpRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingOpsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _clientUuidMeta = const VerificationMeta(
    'clientUuid',
  );
  @override
  late final GeneratedColumn<String> clientUuid = GeneratedColumn<String>(
    'client_uuid',
    aliasedName,
    false,
    additionalChecks: GeneratedColumn.checkTextLength(
      minTextLength: 1,
      maxTextLength: 128,
    ),
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _opMeta = const VerificationMeta('op');
  @override
  late final GeneratedColumn<String> op = GeneratedColumn<String>(
    'op',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _endpointMeta = const VerificationMeta(
    'endpoint',
  );
  @override
  late final GeneratedColumn<String> endpoint = GeneratedColumn<String>(
    'endpoint',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _methodMeta = const VerificationMeta('method');
  @override
  late final GeneratedColumn<String> method = GeneratedColumn<String>(
    'method',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('POST'),
  );
  static const VerificationMeta _payloadMeta = const VerificationMeta(
    'payload',
  );
  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _localFilePathMeta = const VerificationMeta(
    'localFilePath',
  );
  @override
  late final GeneratedColumn<String> localFilePath = GeneratedColumn<String>(
    'local_file_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextAttemptAtMeta = const VerificationMeta(
    'nextAttemptAt',
  );
  @override
  late final GeneratedColumn<DateTime> nextAttemptAt =
      GeneratedColumn<DateTime>(
        'next_attempt_at',
        aliasedName,
        true,
        type: DriftSqlType.dateTime,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _lastErrorMeta = const VerificationMeta(
    'lastError',
  );
  @override
  late final GeneratedColumn<String> lastError = GeneratedColumn<String>(
    'last_error',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  late final GeneratedColumnWithTypeConverter<PendingOpStatus, String> status =
      GeneratedColumn<String>(
        'status',
        aliasedName,
        false,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
        defaultValue: Constant(PendingOpStatus.enAttente.name),
      ).withConverter<PendingOpStatus>($PendingOpsTable.$converterstatus);
  @override
  List<GeneratedColumn> get $columns => [
    clientUuid,
    op,
    endpoint,
    method,
    payload,
    localFilePath,
    createdAt,
    attempts,
    nextAttemptAt,
    lastError,
    status,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_ops';
  @override
  VerificationContext validateIntegrity(
    Insertable<PendingOpRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('client_uuid')) {
      context.handle(
        _clientUuidMeta,
        clientUuid.isAcceptableOrUnknown(data['client_uuid']!, _clientUuidMeta),
      );
    } else if (isInserting) {
      context.missing(_clientUuidMeta);
    }
    if (data.containsKey('op')) {
      context.handle(_opMeta, op.isAcceptableOrUnknown(data['op']!, _opMeta));
    } else if (isInserting) {
      context.missing(_opMeta);
    }
    if (data.containsKey('endpoint')) {
      context.handle(
        _endpointMeta,
        endpoint.isAcceptableOrUnknown(data['endpoint']!, _endpointMeta),
      );
    } else if (isInserting) {
      context.missing(_endpointMeta);
    }
    if (data.containsKey('method')) {
      context.handle(
        _methodMeta,
        method.isAcceptableOrUnknown(data['method']!, _methodMeta),
      );
    }
    if (data.containsKey('payload')) {
      context.handle(
        _payloadMeta,
        payload.isAcceptableOrUnknown(data['payload']!, _payloadMeta),
      );
    } else if (isInserting) {
      context.missing(_payloadMeta);
    }
    if (data.containsKey('local_file_path')) {
      context.handle(
        _localFilePathMeta,
        localFilePath.isAcceptableOrUnknown(
          data['local_file_path']!,
          _localFilePathMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('next_attempt_at')) {
      context.handle(
        _nextAttemptAtMeta,
        nextAttemptAt.isAcceptableOrUnknown(
          data['next_attempt_at']!,
          _nextAttemptAtMeta,
        ),
      );
    }
    if (data.containsKey('last_error')) {
      context.handle(
        _lastErrorMeta,
        lastError.isAcceptableOrUnknown(data['last_error']!, _lastErrorMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {clientUuid};
  @override
  PendingOpRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingOpRow(
      clientUuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}client_uuid'],
      )!,
      op: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}op'],
      )!,
      endpoint: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}endpoint'],
      )!,
      method: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}method'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      localFilePath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_file_path'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      nextAttemptAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}next_attempt_at'],
      ),
      lastError: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_error'],
      ),
      status: $PendingOpsTable.$converterstatus.fromSql(
        attachedDatabase.typeMapping.read(
          DriftSqlType.string,
          data['${effectivePrefix}status'],
        )!,
      ),
    );
  }

  @override
  $PendingOpsTable createAlias(String alias) {
    return $PendingOpsTable(attachedDatabase, alias);
  }

  static JsonTypeConverter2<PendingOpStatus, String, String> $converterstatus =
      const EnumNameConverter<PendingOpStatus>(PendingOpStatus.values);
}

class PendingOpRow extends DataClass implements Insertable<PendingOpRow> {
  /// L'identifiant client, généré **au moment où l'utilisateur agit** et non au
  /// moment de l'envoi.
  ///
  /// C'est lui qui part en `Idempotency-Key` sur les deux actes qui coûtent
  /// (`POST /verifications`, `POST /me/quiz/{uuid}/attempt`). « Une clé
  /// regénérée à l'envoi ne protège de rien » — c'est le scénario qui débite
  /// quelqu'un deux fois pour une seule question.
  final String clientUuid;

  /// Le nom logique de l'acte (voir `PendingOpKind`).
  final String op;

  /// Le chemin d'API visé, gardé tel quel : il porte le slug ou l'uuid de la
  /// ressource et n'a pas à être reconstruit au rejeu.
  final String endpoint;
  final String method;

  /// La charge utile, sérialisée en JSON.
  final String payload;

  /// La copie locale du fichier, pour une soumission de vérification.
  ///
  /// POURQUOI la file garde le fichier et pas seulement son identifiant : le
  /// téléversement **expire au bout de deux heures** côté serveur, et un rejeu
  /// au-delà doit re-téléverser (04-RESEAU §4.10).
  final String? localFilePath;
  final DateTime createdAt;
  final int attempts;

  /// Le prochain créneau de rejeu — c'est ici que vit le recul exponentiel.
  final DateTime? nextAttemptAt;

  /// Le dernier motif d'échec, **déjà expurgé**, pour l'afficher dans le détail
  /// du bandeau.
  final String? lastError;
  final PendingOpStatus status;
  const PendingOpRow({
    required this.clientUuid,
    required this.op,
    required this.endpoint,
    required this.method,
    required this.payload,
    this.localFilePath,
    required this.createdAt,
    required this.attempts,
    this.nextAttemptAt,
    this.lastError,
    required this.status,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['client_uuid'] = Variable<String>(clientUuid);
    map['op'] = Variable<String>(op);
    map['endpoint'] = Variable<String>(endpoint);
    map['method'] = Variable<String>(method);
    map['payload'] = Variable<String>(payload);
    if (!nullToAbsent || localFilePath != null) {
      map['local_file_path'] = Variable<String>(localFilePath);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['attempts'] = Variable<int>(attempts);
    if (!nullToAbsent || nextAttemptAt != null) {
      map['next_attempt_at'] = Variable<DateTime>(nextAttemptAt);
    }
    if (!nullToAbsent || lastError != null) {
      map['last_error'] = Variable<String>(lastError);
    }
    {
      map['status'] = Variable<String>(
        $PendingOpsTable.$converterstatus.toSql(status),
      );
    }
    return map;
  }

  PendingOpsCompanion toCompanion(bool nullToAbsent) {
    return PendingOpsCompanion(
      clientUuid: Value(clientUuid),
      op: Value(op),
      endpoint: Value(endpoint),
      method: Value(method),
      payload: Value(payload),
      localFilePath: localFilePath == null && nullToAbsent
          ? const Value.absent()
          : Value(localFilePath),
      createdAt: Value(createdAt),
      attempts: Value(attempts),
      nextAttemptAt: nextAttemptAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextAttemptAt),
      lastError: lastError == null && nullToAbsent
          ? const Value.absent()
          : Value(lastError),
      status: Value(status),
    );
  }

  factory PendingOpRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingOpRow(
      clientUuid: serializer.fromJson<String>(json['clientUuid']),
      op: serializer.fromJson<String>(json['op']),
      endpoint: serializer.fromJson<String>(json['endpoint']),
      method: serializer.fromJson<String>(json['method']),
      payload: serializer.fromJson<String>(json['payload']),
      localFilePath: serializer.fromJson<String?>(json['localFilePath']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      attempts: serializer.fromJson<int>(json['attempts']),
      nextAttemptAt: serializer.fromJson<DateTime?>(json['nextAttemptAt']),
      lastError: serializer.fromJson<String?>(json['lastError']),
      status: $PendingOpsTable.$converterstatus.fromJson(
        serializer.fromJson<String>(json['status']),
      ),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'clientUuid': serializer.toJson<String>(clientUuid),
      'op': serializer.toJson<String>(op),
      'endpoint': serializer.toJson<String>(endpoint),
      'method': serializer.toJson<String>(method),
      'payload': serializer.toJson<String>(payload),
      'localFilePath': serializer.toJson<String?>(localFilePath),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'attempts': serializer.toJson<int>(attempts),
      'nextAttemptAt': serializer.toJson<DateTime?>(nextAttemptAt),
      'lastError': serializer.toJson<String?>(lastError),
      'status': serializer.toJson<String>(
        $PendingOpsTable.$converterstatus.toJson(status),
      ),
    };
  }

  PendingOpRow copyWith({
    String? clientUuid,
    String? op,
    String? endpoint,
    String? method,
    String? payload,
    Value<String?> localFilePath = const Value.absent(),
    DateTime? createdAt,
    int? attempts,
    Value<DateTime?> nextAttemptAt = const Value.absent(),
    Value<String?> lastError = const Value.absent(),
    PendingOpStatus? status,
  }) => PendingOpRow(
    clientUuid: clientUuid ?? this.clientUuid,
    op: op ?? this.op,
    endpoint: endpoint ?? this.endpoint,
    method: method ?? this.method,
    payload: payload ?? this.payload,
    localFilePath: localFilePath.present
        ? localFilePath.value
        : this.localFilePath,
    createdAt: createdAt ?? this.createdAt,
    attempts: attempts ?? this.attempts,
    nextAttemptAt: nextAttemptAt.present
        ? nextAttemptAt.value
        : this.nextAttemptAt,
    lastError: lastError.present ? lastError.value : this.lastError,
    status: status ?? this.status,
  );
  PendingOpRow copyWithCompanion(PendingOpsCompanion data) {
    return PendingOpRow(
      clientUuid: data.clientUuid.present
          ? data.clientUuid.value
          : this.clientUuid,
      op: data.op.present ? data.op.value : this.op,
      endpoint: data.endpoint.present ? data.endpoint.value : this.endpoint,
      method: data.method.present ? data.method.value : this.method,
      payload: data.payload.present ? data.payload.value : this.payload,
      localFilePath: data.localFilePath.present
          ? data.localFilePath.value
          : this.localFilePath,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      nextAttemptAt: data.nextAttemptAt.present
          ? data.nextAttemptAt.value
          : this.nextAttemptAt,
      lastError: data.lastError.present ? data.lastError.value : this.lastError,
      status: data.status.present ? data.status.value : this.status,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingOpRow(')
          ..write('clientUuid: $clientUuid, ')
          ..write('op: $op, ')
          ..write('endpoint: $endpoint, ')
          ..write('method: $method, ')
          ..write('payload: $payload, ')
          ..write('localFilePath: $localFilePath, ')
          ..write('createdAt: $createdAt, ')
          ..write('attempts: $attempts, ')
          ..write('nextAttemptAt: $nextAttemptAt, ')
          ..write('lastError: $lastError, ')
          ..write('status: $status')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    clientUuid,
    op,
    endpoint,
    method,
    payload,
    localFilePath,
    createdAt,
    attempts,
    nextAttemptAt,
    lastError,
    status,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingOpRow &&
          other.clientUuid == this.clientUuid &&
          other.op == this.op &&
          other.endpoint == this.endpoint &&
          other.method == this.method &&
          other.payload == this.payload &&
          other.localFilePath == this.localFilePath &&
          other.createdAt == this.createdAt &&
          other.attempts == this.attempts &&
          other.nextAttemptAt == this.nextAttemptAt &&
          other.lastError == this.lastError &&
          other.status == this.status);
}

class PendingOpsCompanion extends UpdateCompanion<PendingOpRow> {
  final Value<String> clientUuid;
  final Value<String> op;
  final Value<String> endpoint;
  final Value<String> method;
  final Value<String> payload;
  final Value<String?> localFilePath;
  final Value<DateTime> createdAt;
  final Value<int> attempts;
  final Value<DateTime?> nextAttemptAt;
  final Value<String?> lastError;
  final Value<PendingOpStatus> status;
  final Value<int> rowid;
  const PendingOpsCompanion({
    this.clientUuid = const Value.absent(),
    this.op = const Value.absent(),
    this.endpoint = const Value.absent(),
    this.method = const Value.absent(),
    this.payload = const Value.absent(),
    this.localFilePath = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextAttemptAt = const Value.absent(),
    this.lastError = const Value.absent(),
    this.status = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PendingOpsCompanion.insert({
    required String clientUuid,
    required String op,
    required String endpoint,
    this.method = const Value.absent(),
    required String payload,
    this.localFilePath = const Value.absent(),
    required DateTime createdAt,
    this.attempts = const Value.absent(),
    this.nextAttemptAt = const Value.absent(),
    this.lastError = const Value.absent(),
    this.status = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : clientUuid = Value(clientUuid),
       op = Value(op),
       endpoint = Value(endpoint),
       payload = Value(payload),
       createdAt = Value(createdAt);
  static Insertable<PendingOpRow> custom({
    Expression<String>? clientUuid,
    Expression<String>? op,
    Expression<String>? endpoint,
    Expression<String>? method,
    Expression<String>? payload,
    Expression<String>? localFilePath,
    Expression<DateTime>? createdAt,
    Expression<int>? attempts,
    Expression<DateTime>? nextAttemptAt,
    Expression<String>? lastError,
    Expression<String>? status,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (clientUuid != null) 'client_uuid': clientUuid,
      if (op != null) 'op': op,
      if (endpoint != null) 'endpoint': endpoint,
      if (method != null) 'method': method,
      if (payload != null) 'payload': payload,
      if (localFilePath != null) 'local_file_path': localFilePath,
      if (createdAt != null) 'created_at': createdAt,
      if (attempts != null) 'attempts': attempts,
      if (nextAttemptAt != null) 'next_attempt_at': nextAttemptAt,
      if (lastError != null) 'last_error': lastError,
      if (status != null) 'status': status,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PendingOpsCompanion copyWith({
    Value<String>? clientUuid,
    Value<String>? op,
    Value<String>? endpoint,
    Value<String>? method,
    Value<String>? payload,
    Value<String?>? localFilePath,
    Value<DateTime>? createdAt,
    Value<int>? attempts,
    Value<DateTime?>? nextAttemptAt,
    Value<String?>? lastError,
    Value<PendingOpStatus>? status,
    Value<int>? rowid,
  }) {
    return PendingOpsCompanion(
      clientUuid: clientUuid ?? this.clientUuid,
      op: op ?? this.op,
      endpoint: endpoint ?? this.endpoint,
      method: method ?? this.method,
      payload: payload ?? this.payload,
      localFilePath: localFilePath ?? this.localFilePath,
      createdAt: createdAt ?? this.createdAt,
      attempts: attempts ?? this.attempts,
      nextAttemptAt: nextAttemptAt ?? this.nextAttemptAt,
      lastError: lastError ?? this.lastError,
      status: status ?? this.status,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (clientUuid.present) {
      map['client_uuid'] = Variable<String>(clientUuid.value);
    }
    if (op.present) {
      map['op'] = Variable<String>(op.value);
    }
    if (endpoint.present) {
      map['endpoint'] = Variable<String>(endpoint.value);
    }
    if (method.present) {
      map['method'] = Variable<String>(method.value);
    }
    if (payload.present) {
      map['payload'] = Variable<String>(payload.value);
    }
    if (localFilePath.present) {
      map['local_file_path'] = Variable<String>(localFilePath.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (nextAttemptAt.present) {
      map['next_attempt_at'] = Variable<DateTime>(nextAttemptAt.value);
    }
    if (lastError.present) {
      map['last_error'] = Variable<String>(lastError.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(
        $PendingOpsTable.$converterstatus.toSql(status.value),
      );
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingOpsCompanion(')
          ..write('clientUuid: $clientUuid, ')
          ..write('op: $op, ')
          ..write('endpoint: $endpoint, ')
          ..write('method: $method, ')
          ..write('payload: $payload, ')
          ..write('localFilePath: $localFilePath, ')
          ..write('createdAt: $createdAt, ')
          ..write('attempts: $attempts, ')
          ..write('nextAttemptAt: $nextAttemptAt, ')
          ..write('lastError: $lastError, ')
          ..write('status: $status, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final CompteCache compteCache = CompteCache(this);
  late final CommunityCache communityCache = CommunityCache(this);
  late final LmsCatalogue lmsCatalogue = LmsCatalogue(this);
  late final LmsCours lmsCours = LmsCours(this);
  late final LmsLecon lmsLecon = LmsLecon(this);
  late final LmsLectureActive lmsLectureActive = LmsLectureActive(this);
  late final LmsCertificat lmsCertificat = LmsCertificat(this);
  late final LmsSeances lmsSeances = LmsSeances(this);
  late final VerificationCache verificationCache = VerificationCache(this);
  late final AnnuaireOutilsCache annuaireOutilsCache = AnnuaireOutilsCache(
    this,
  );
  late final TableauDeBordCache tableauDeBordCache = TableauDeBordCache(this);
  late final RepriseDeCoursCache repriseDeCoursCache = RepriseDeCoursCache(
    this,
  );
  late final $PendingOpsTable pendingOps = $PendingOpsTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    compteCache,
    communityCache,
    lmsCatalogue,
    lmsCours,
    lmsLecon,
    lmsLectureActive,
    lmsCertificat,
    lmsSeances,
    verificationCache,
    annuaireOutilsCache,
    tableauDeBordCache,
    repriseDeCoursCache,
    pendingOps,
  ];
}

typedef $CompteCacheCreateCompanionBuilder =
    CompteCacheCompanion Function({
      required String ressource,
      required String chargeUtile,
      required DateTime recupereLe,
      Value<int> rowid,
    });
typedef $CompteCacheUpdateCompanionBuilder =
    CompteCacheCompanion Function({
      Value<String> ressource,
      Value<String> chargeUtile,
      Value<DateTime> recupereLe,
      Value<int> rowid,
    });

class $CompteCacheFilterComposer extends Composer<_$AppDatabase, CompteCache> {
  $CompteCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get ressource => $composableBuilder(
    column: $table.ressource,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $CompteCacheOrderingComposer
    extends Composer<_$AppDatabase, CompteCache> {
  $CompteCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get ressource => $composableBuilder(
    column: $table.ressource,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $CompteCacheAnnotationComposer
    extends Composer<_$AppDatabase, CompteCache> {
  $CompteCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get ressource =>
      $composableBuilder(column: $table.ressource, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );
}

class $CompteCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          CompteCache,
          CompteCacheRow,
          $CompteCacheFilterComposer,
          $CompteCacheOrderingComposer,
          $CompteCacheAnnotationComposer,
          $CompteCacheCreateCompanionBuilder,
          $CompteCacheUpdateCompanionBuilder,
          (
            CompteCacheRow,
            BaseReferences<_$AppDatabase, CompteCache, CompteCacheRow>,
          ),
          CompteCacheRow,
          PrefetchHooks Function()
        > {
  $CompteCacheTableManager(_$AppDatabase db, CompteCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $CompteCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $CompteCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $CompteCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> ressource = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<DateTime> recupereLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CompteCacheCompanion(
                ressource: ressource,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String ressource,
                required String chargeUtile,
                required DateTime recupereLe,
                Value<int> rowid = const Value.absent(),
              }) => CompteCacheCompanion.insert(
                ressource: ressource,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $CompteCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      CompteCache,
      CompteCacheRow,
      $CompteCacheFilterComposer,
      $CompteCacheOrderingComposer,
      $CompteCacheAnnotationComposer,
      $CompteCacheCreateCompanionBuilder,
      $CompteCacheUpdateCompanionBuilder,
      (
        CompteCacheRow,
        BaseReferences<_$AppDatabase, CompteCache, CompteCacheRow>,
      ),
      CompteCacheRow,
      PrefetchHooks Function()
    >;
typedef $CommunityCacheCreateCompanionBuilder =
    CommunityCacheCompanion Function({
      required String cle,
      required String chargeUtile,
      required DateTime recupereLe,
      Value<int> rowid,
    });
typedef $CommunityCacheUpdateCompanionBuilder =
    CommunityCacheCompanion Function({
      Value<String> cle,
      Value<String> chargeUtile,
      Value<DateTime> recupereLe,
      Value<int> rowid,
    });

class $CommunityCacheFilterComposer
    extends Composer<_$AppDatabase, CommunityCache> {
  $CommunityCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $CommunityCacheOrderingComposer
    extends Composer<_$AppDatabase, CommunityCache> {
  $CommunityCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $CommunityCacheAnnotationComposer
    extends Composer<_$AppDatabase, CommunityCache> {
  $CommunityCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cle =>
      $composableBuilder(column: $table.cle, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );
}

class $CommunityCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          CommunityCache,
          CommunityCacheRow,
          $CommunityCacheFilterComposer,
          $CommunityCacheOrderingComposer,
          $CommunityCacheAnnotationComposer,
          $CommunityCacheCreateCompanionBuilder,
          $CommunityCacheUpdateCompanionBuilder,
          (
            CommunityCacheRow,
            BaseReferences<_$AppDatabase, CommunityCache, CommunityCacheRow>,
          ),
          CommunityCacheRow,
          PrefetchHooks Function()
        > {
  $CommunityCacheTableManager(_$AppDatabase db, CommunityCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $CommunityCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $CommunityCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $CommunityCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cle = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<DateTime> recupereLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CommunityCacheCompanion(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String cle,
                required String chargeUtile,
                required DateTime recupereLe,
                Value<int> rowid = const Value.absent(),
              }) => CommunityCacheCompanion.insert(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $CommunityCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      CommunityCache,
      CommunityCacheRow,
      $CommunityCacheFilterComposer,
      $CommunityCacheOrderingComposer,
      $CommunityCacheAnnotationComposer,
      $CommunityCacheCreateCompanionBuilder,
      $CommunityCacheUpdateCompanionBuilder,
      (
        CommunityCacheRow,
        BaseReferences<_$AppDatabase, CommunityCache, CommunityCacheRow>,
      ),
      CommunityCacheRow,
      PrefetchHooks Function()
    >;
typedef $LmsCatalogueCreateCompanionBuilder =
    LmsCatalogueCompanion Function({
      required String cle,
      required String chargeUtile,
      required int recupereLe,
      Value<int> rowid,
    });
typedef $LmsCatalogueUpdateCompanionBuilder =
    LmsCatalogueCompanion Function({
      Value<String> cle,
      Value<String> chargeUtile,
      Value<int> recupereLe,
      Value<int> rowid,
    });

class $LmsCatalogueFilterComposer
    extends Composer<_$AppDatabase, LmsCatalogue> {
  $LmsCatalogueFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsCatalogueOrderingComposer
    extends Composer<_$AppDatabase, LmsCatalogue> {
  $LmsCatalogueOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsCatalogueAnnotationComposer
    extends Composer<_$AppDatabase, LmsCatalogue> {
  $LmsCatalogueAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cle =>
      $composableBuilder(column: $table.cle, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );
}

class $LmsCatalogueTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsCatalogue,
          LmsCatalogueEntree,
          $LmsCatalogueFilterComposer,
          $LmsCatalogueOrderingComposer,
          $LmsCatalogueAnnotationComposer,
          $LmsCatalogueCreateCompanionBuilder,
          $LmsCatalogueUpdateCompanionBuilder,
          (
            LmsCatalogueEntree,
            BaseReferences<_$AppDatabase, LmsCatalogue, LmsCatalogueEntree>,
          ),
          LmsCatalogueEntree,
          PrefetchHooks Function()
        > {
  $LmsCatalogueTableManager(_$AppDatabase db, LmsCatalogue table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsCatalogueFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsCatalogueOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsCatalogueAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cle = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<int> recupereLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsCatalogueCompanion(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String cle,
                required String chargeUtile,
                required int recupereLe,
                Value<int> rowid = const Value.absent(),
              }) => LmsCatalogueCompanion.insert(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsCatalogueProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsCatalogue,
      LmsCatalogueEntree,
      $LmsCatalogueFilterComposer,
      $LmsCatalogueOrderingComposer,
      $LmsCatalogueAnnotationComposer,
      $LmsCatalogueCreateCompanionBuilder,
      $LmsCatalogueUpdateCompanionBuilder,
      (
        LmsCatalogueEntree,
        BaseReferences<_$AppDatabase, LmsCatalogue, LmsCatalogueEntree>,
      ),
      LmsCatalogueEntree,
      PrefetchHooks Function()
    >;
typedef $LmsCoursCreateCompanionBuilder =
    LmsCoursCompanion Function({
      required String slug,
      required String chargeUtile,
      required String meta,
      required int recupereLe,
      Value<int?> telechargeLe,
      Value<int> octets,
      Value<int> rowid,
    });
typedef $LmsCoursUpdateCompanionBuilder =
    LmsCoursCompanion Function({
      Value<String> slug,
      Value<String> chargeUtile,
      Value<String> meta,
      Value<int> recupereLe,
      Value<int?> telechargeLe,
      Value<int> octets,
      Value<int> rowid,
    });

class $LmsCoursFilterComposer extends Composer<_$AppDatabase, LmsCours> {
  $LmsCoursFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get slug => $composableBuilder(
    column: $table.slug,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get meta => $composableBuilder(
    column: $table.meta,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get telechargeLe => $composableBuilder(
    column: $table.telechargeLe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get octets => $composableBuilder(
    column: $table.octets,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsCoursOrderingComposer extends Composer<_$AppDatabase, LmsCours> {
  $LmsCoursOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get slug => $composableBuilder(
    column: $table.slug,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get meta => $composableBuilder(
    column: $table.meta,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get telechargeLe => $composableBuilder(
    column: $table.telechargeLe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get octets => $composableBuilder(
    column: $table.octets,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsCoursAnnotationComposer extends Composer<_$AppDatabase, LmsCours> {
  $LmsCoursAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get slug =>
      $composableBuilder(column: $table.slug, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<String> get meta =>
      $composableBuilder(column: $table.meta, builder: (column) => column);

  GeneratedColumn<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );

  GeneratedColumn<int> get telechargeLe => $composableBuilder(
    column: $table.telechargeLe,
    builder: (column) => column,
  );

  GeneratedColumn<int> get octets =>
      $composableBuilder(column: $table.octets, builder: (column) => column);
}

class $LmsCoursTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsCours,
          LmsCoursEntree,
          $LmsCoursFilterComposer,
          $LmsCoursOrderingComposer,
          $LmsCoursAnnotationComposer,
          $LmsCoursCreateCompanionBuilder,
          $LmsCoursUpdateCompanionBuilder,
          (
            LmsCoursEntree,
            BaseReferences<_$AppDatabase, LmsCours, LmsCoursEntree>,
          ),
          LmsCoursEntree,
          PrefetchHooks Function()
        > {
  $LmsCoursTableManager(_$AppDatabase db, LmsCours table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsCoursFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsCoursOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsCoursAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> slug = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<String> meta = const Value.absent(),
                Value<int> recupereLe = const Value.absent(),
                Value<int?> telechargeLe = const Value.absent(),
                Value<int> octets = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsCoursCompanion(
                slug: slug,
                chargeUtile: chargeUtile,
                meta: meta,
                recupereLe: recupereLe,
                telechargeLe: telechargeLe,
                octets: octets,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String slug,
                required String chargeUtile,
                required String meta,
                required int recupereLe,
                Value<int?> telechargeLe = const Value.absent(),
                Value<int> octets = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsCoursCompanion.insert(
                slug: slug,
                chargeUtile: chargeUtile,
                meta: meta,
                recupereLe: recupereLe,
                telechargeLe: telechargeLe,
                octets: octets,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsCoursProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsCours,
      LmsCoursEntree,
      $LmsCoursFilterComposer,
      $LmsCoursOrderingComposer,
      $LmsCoursAnnotationComposer,
      $LmsCoursCreateCompanionBuilder,
      $LmsCoursUpdateCompanionBuilder,
      (LmsCoursEntree, BaseReferences<_$AppDatabase, LmsCours, LmsCoursEntree>),
      LmsCoursEntree,
      PrefetchHooks Function()
    >;
typedef $LmsLeconCreateCompanionBuilder =
    LmsLeconCompanion Function({
      required String uuid,
      required String coursSlug,
      required String chargeUtile,
      required int secondesRequises,
      required int recupereLe,
      Value<int> octets,
      Value<int> rowid,
    });
typedef $LmsLeconUpdateCompanionBuilder =
    LmsLeconCompanion Function({
      Value<String> uuid,
      Value<String> coursSlug,
      Value<String> chargeUtile,
      Value<int> secondesRequises,
      Value<int> recupereLe,
      Value<int> octets,
      Value<int> rowid,
    });

class $LmsLeconFilterComposer extends Composer<_$AppDatabase, LmsLecon> {
  $LmsLeconFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get uuid => $composableBuilder(
    column: $table.uuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get coursSlug => $composableBuilder(
    column: $table.coursSlug,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get secondesRequises => $composableBuilder(
    column: $table.secondesRequises,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get octets => $composableBuilder(
    column: $table.octets,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsLeconOrderingComposer extends Composer<_$AppDatabase, LmsLecon> {
  $LmsLeconOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get uuid => $composableBuilder(
    column: $table.uuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get coursSlug => $composableBuilder(
    column: $table.coursSlug,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get secondesRequises => $composableBuilder(
    column: $table.secondesRequises,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get octets => $composableBuilder(
    column: $table.octets,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsLeconAnnotationComposer extends Composer<_$AppDatabase, LmsLecon> {
  $LmsLeconAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get uuid =>
      $composableBuilder(column: $table.uuid, builder: (column) => column);

  GeneratedColumn<String> get coursSlug =>
      $composableBuilder(column: $table.coursSlug, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<int> get secondesRequises => $composableBuilder(
    column: $table.secondesRequises,
    builder: (column) => column,
  );

  GeneratedColumn<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );

  GeneratedColumn<int> get octets =>
      $composableBuilder(column: $table.octets, builder: (column) => column);
}

class $LmsLeconTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsLecon,
          LmsLeconEntree,
          $LmsLeconFilterComposer,
          $LmsLeconOrderingComposer,
          $LmsLeconAnnotationComposer,
          $LmsLeconCreateCompanionBuilder,
          $LmsLeconUpdateCompanionBuilder,
          (
            LmsLeconEntree,
            BaseReferences<_$AppDatabase, LmsLecon, LmsLeconEntree>,
          ),
          LmsLeconEntree,
          PrefetchHooks Function()
        > {
  $LmsLeconTableManager(_$AppDatabase db, LmsLecon table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsLeconFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsLeconOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsLeconAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> uuid = const Value.absent(),
                Value<String> coursSlug = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<int> secondesRequises = const Value.absent(),
                Value<int> recupereLe = const Value.absent(),
                Value<int> octets = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsLeconCompanion(
                uuid: uuid,
                coursSlug: coursSlug,
                chargeUtile: chargeUtile,
                secondesRequises: secondesRequises,
                recupereLe: recupereLe,
                octets: octets,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String uuid,
                required String coursSlug,
                required String chargeUtile,
                required int secondesRequises,
                required int recupereLe,
                Value<int> octets = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsLeconCompanion.insert(
                uuid: uuid,
                coursSlug: coursSlug,
                chargeUtile: chargeUtile,
                secondesRequises: secondesRequises,
                recupereLe: recupereLe,
                octets: octets,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsLeconProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsLecon,
      LmsLeconEntree,
      $LmsLeconFilterComposer,
      $LmsLeconOrderingComposer,
      $LmsLeconAnnotationComposer,
      $LmsLeconCreateCompanionBuilder,
      $LmsLeconUpdateCompanionBuilder,
      (LmsLeconEntree, BaseReferences<_$AppDatabase, LmsLecon, LmsLeconEntree>),
      LmsLeconEntree,
      PrefetchHooks Function()
    >;
typedef $LmsLectureActiveCreateCompanionBuilder =
    LmsLectureActiveCompanion Function({
      required String leconUuid,
      Value<int> secondes,
      required int majLe,
      Value<int> rowid,
    });
typedef $LmsLectureActiveUpdateCompanionBuilder =
    LmsLectureActiveCompanion Function({
      Value<String> leconUuid,
      Value<int> secondes,
      Value<int> majLe,
      Value<int> rowid,
    });

class $LmsLectureActiveFilterComposer
    extends Composer<_$AppDatabase, LmsLectureActive> {
  $LmsLectureActiveFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get leconUuid => $composableBuilder(
    column: $table.leconUuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get secondes => $composableBuilder(
    column: $table.secondes,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get majLe => $composableBuilder(
    column: $table.majLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsLectureActiveOrderingComposer
    extends Composer<_$AppDatabase, LmsLectureActive> {
  $LmsLectureActiveOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get leconUuid => $composableBuilder(
    column: $table.leconUuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get secondes => $composableBuilder(
    column: $table.secondes,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get majLe => $composableBuilder(
    column: $table.majLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsLectureActiveAnnotationComposer
    extends Composer<_$AppDatabase, LmsLectureActive> {
  $LmsLectureActiveAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get leconUuid =>
      $composableBuilder(column: $table.leconUuid, builder: (column) => column);

  GeneratedColumn<int> get secondes =>
      $composableBuilder(column: $table.secondes, builder: (column) => column);

  GeneratedColumn<int> get majLe =>
      $composableBuilder(column: $table.majLe, builder: (column) => column);
}

class $LmsLectureActiveTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsLectureActive,
          LmsLectureActiveEntree,
          $LmsLectureActiveFilterComposer,
          $LmsLectureActiveOrderingComposer,
          $LmsLectureActiveAnnotationComposer,
          $LmsLectureActiveCreateCompanionBuilder,
          $LmsLectureActiveUpdateCompanionBuilder,
          (
            LmsLectureActiveEntree,
            BaseReferences<
              _$AppDatabase,
              LmsLectureActive,
              LmsLectureActiveEntree
            >,
          ),
          LmsLectureActiveEntree,
          PrefetchHooks Function()
        > {
  $LmsLectureActiveTableManager(_$AppDatabase db, LmsLectureActive table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsLectureActiveFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsLectureActiveOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsLectureActiveAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> leconUuid = const Value.absent(),
                Value<int> secondes = const Value.absent(),
                Value<int> majLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsLectureActiveCompanion(
                leconUuid: leconUuid,
                secondes: secondes,
                majLe: majLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String leconUuid,
                Value<int> secondes = const Value.absent(),
                required int majLe,
                Value<int> rowid = const Value.absent(),
              }) => LmsLectureActiveCompanion.insert(
                leconUuid: leconUuid,
                secondes: secondes,
                majLe: majLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsLectureActiveProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsLectureActive,
      LmsLectureActiveEntree,
      $LmsLectureActiveFilterComposer,
      $LmsLectureActiveOrderingComposer,
      $LmsLectureActiveAnnotationComposer,
      $LmsLectureActiveCreateCompanionBuilder,
      $LmsLectureActiveUpdateCompanionBuilder,
      (
        LmsLectureActiveEntree,
        BaseReferences<_$AppDatabase, LmsLectureActive, LmsLectureActiveEntree>,
      ),
      LmsLectureActiveEntree,
      PrefetchHooks Function()
    >;
typedef $LmsCertificatCreateCompanionBuilder =
    LmsCertificatCompanion Function({
      required String code,
      required String chargeUtile,
      required int recupereLe,
      Value<int> rowid,
    });
typedef $LmsCertificatUpdateCompanionBuilder =
    LmsCertificatCompanion Function({
      Value<String> code,
      Value<String> chargeUtile,
      Value<int> recupereLe,
      Value<int> rowid,
    });

class $LmsCertificatFilterComposer
    extends Composer<_$AppDatabase, LmsCertificat> {
  $LmsCertificatFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get code => $composableBuilder(
    column: $table.code,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsCertificatOrderingComposer
    extends Composer<_$AppDatabase, LmsCertificat> {
  $LmsCertificatOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get code => $composableBuilder(
    column: $table.code,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsCertificatAnnotationComposer
    extends Composer<_$AppDatabase, LmsCertificat> {
  $LmsCertificatAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get code =>
      $composableBuilder(column: $table.code, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );
}

class $LmsCertificatTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsCertificat,
          LmsCertificatEntree,
          $LmsCertificatFilterComposer,
          $LmsCertificatOrderingComposer,
          $LmsCertificatAnnotationComposer,
          $LmsCertificatCreateCompanionBuilder,
          $LmsCertificatUpdateCompanionBuilder,
          (
            LmsCertificatEntree,
            BaseReferences<_$AppDatabase, LmsCertificat, LmsCertificatEntree>,
          ),
          LmsCertificatEntree,
          PrefetchHooks Function()
        > {
  $LmsCertificatTableManager(_$AppDatabase db, LmsCertificat table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsCertificatFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsCertificatOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsCertificatAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> code = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<int> recupereLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsCertificatCompanion(
                code: code,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String code,
                required String chargeUtile,
                required int recupereLe,
                Value<int> rowid = const Value.absent(),
              }) => LmsCertificatCompanion.insert(
                code: code,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsCertificatProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsCertificat,
      LmsCertificatEntree,
      $LmsCertificatFilterComposer,
      $LmsCertificatOrderingComposer,
      $LmsCertificatAnnotationComposer,
      $LmsCertificatCreateCompanionBuilder,
      $LmsCertificatUpdateCompanionBuilder,
      (
        LmsCertificatEntree,
        BaseReferences<_$AppDatabase, LmsCertificat, LmsCertificatEntree>,
      ),
      LmsCertificatEntree,
      PrefetchHooks Function()
    >;
typedef $LmsSeancesCreateCompanionBuilder =
    LmsSeancesCompanion Function({
      required String cle,
      required String chargeUtile,
      required int recupereLe,
      Value<int> rowid,
    });
typedef $LmsSeancesUpdateCompanionBuilder =
    LmsSeancesCompanion Function({
      Value<String> cle,
      Value<String> chargeUtile,
      Value<int> recupereLe,
      Value<int> rowid,
    });

class $LmsSeancesFilterComposer extends Composer<_$AppDatabase, LmsSeances> {
  $LmsSeancesFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $LmsSeancesOrderingComposer extends Composer<_$AppDatabase, LmsSeances> {
  $LmsSeancesOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $LmsSeancesAnnotationComposer
    extends Composer<_$AppDatabase, LmsSeances> {
  $LmsSeancesAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cle =>
      $composableBuilder(column: $table.cle, builder: (column) => column);

  GeneratedColumn<String> get chargeUtile => $composableBuilder(
    column: $table.chargeUtile,
    builder: (column) => column,
  );

  GeneratedColumn<int> get recupereLe => $composableBuilder(
    column: $table.recupereLe,
    builder: (column) => column,
  );
}

class $LmsSeancesTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          LmsSeances,
          LmsSeancesEntree,
          $LmsSeancesFilterComposer,
          $LmsSeancesOrderingComposer,
          $LmsSeancesAnnotationComposer,
          $LmsSeancesCreateCompanionBuilder,
          $LmsSeancesUpdateCompanionBuilder,
          (
            LmsSeancesEntree,
            BaseReferences<_$AppDatabase, LmsSeances, LmsSeancesEntree>,
          ),
          LmsSeancesEntree,
          PrefetchHooks Function()
        > {
  $LmsSeancesTableManager(_$AppDatabase db, LmsSeances table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $LmsSeancesFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $LmsSeancesOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $LmsSeancesAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cle = const Value.absent(),
                Value<String> chargeUtile = const Value.absent(),
                Value<int> recupereLe = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LmsSeancesCompanion(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String cle,
                required String chargeUtile,
                required int recupereLe,
                Value<int> rowid = const Value.absent(),
              }) => LmsSeancesCompanion.insert(
                cle: cle,
                chargeUtile: chargeUtile,
                recupereLe: recupereLe,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $LmsSeancesProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      LmsSeances,
      LmsSeancesEntree,
      $LmsSeancesFilterComposer,
      $LmsSeancesOrderingComposer,
      $LmsSeancesAnnotationComposer,
      $LmsSeancesCreateCompanionBuilder,
      $LmsSeancesUpdateCompanionBuilder,
      (
        LmsSeancesEntree,
        BaseReferences<_$AppDatabase, LmsSeances, LmsSeancesEntree>,
      ),
      LmsSeancesEntree,
      PrefetchHooks Function()
    >;
typedef $VerificationCacheCreateCompanionBuilder =
    VerificationCacheCompanion Function({
      required String uuid,
      required String chargeJson,
      required bool terminee,
      required int rang,
      Value<DateTime?> soumiseLe,
      required DateTime fetchedAt,
      Value<int> rowid,
    });
typedef $VerificationCacheUpdateCompanionBuilder =
    VerificationCacheCompanion Function({
      Value<String> uuid,
      Value<String> chargeJson,
      Value<bool> terminee,
      Value<int> rang,
      Value<DateTime?> soumiseLe,
      Value<DateTime> fetchedAt,
      Value<int> rowid,
    });

class $VerificationCacheFilterComposer
    extends Composer<_$AppDatabase, VerificationCache> {
  $VerificationCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get uuid => $composableBuilder(
    column: $table.uuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get terminee => $composableBuilder(
    column: $table.terminee,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get rang => $composableBuilder(
    column: $table.rang,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get soumiseLe => $composableBuilder(
    column: $table.soumiseLe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $VerificationCacheOrderingComposer
    extends Composer<_$AppDatabase, VerificationCache> {
  $VerificationCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get uuid => $composableBuilder(
    column: $table.uuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get terminee => $composableBuilder(
    column: $table.terminee,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get rang => $composableBuilder(
    column: $table.rang,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get soumiseLe => $composableBuilder(
    column: $table.soumiseLe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $VerificationCacheAnnotationComposer
    extends Composer<_$AppDatabase, VerificationCache> {
  $VerificationCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get uuid =>
      $composableBuilder(column: $table.uuid, builder: (column) => column);

  GeneratedColumn<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get terminee =>
      $composableBuilder(column: $table.terminee, builder: (column) => column);

  GeneratedColumn<int> get rang =>
      $composableBuilder(column: $table.rang, builder: (column) => column);

  GeneratedColumn<DateTime> get soumiseLe =>
      $composableBuilder(column: $table.soumiseLe, builder: (column) => column);

  GeneratedColumn<DateTime> get fetchedAt =>
      $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $VerificationCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          VerificationCache,
          VerificationCacheData,
          $VerificationCacheFilterComposer,
          $VerificationCacheOrderingComposer,
          $VerificationCacheAnnotationComposer,
          $VerificationCacheCreateCompanionBuilder,
          $VerificationCacheUpdateCompanionBuilder,
          (
            VerificationCacheData,
            BaseReferences<
              _$AppDatabase,
              VerificationCache,
              VerificationCacheData
            >,
          ),
          VerificationCacheData,
          PrefetchHooks Function()
        > {
  $VerificationCacheTableManager(_$AppDatabase db, VerificationCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $VerificationCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $VerificationCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $VerificationCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> uuid = const Value.absent(),
                Value<String> chargeJson = const Value.absent(),
                Value<bool> terminee = const Value.absent(),
                Value<int> rang = const Value.absent(),
                Value<DateTime?> soumiseLe = const Value.absent(),
                Value<DateTime> fetchedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => VerificationCacheCompanion(
                uuid: uuid,
                chargeJson: chargeJson,
                terminee: terminee,
                rang: rang,
                soumiseLe: soumiseLe,
                fetchedAt: fetchedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String uuid,
                required String chargeJson,
                required bool terminee,
                required int rang,
                Value<DateTime?> soumiseLe = const Value.absent(),
                required DateTime fetchedAt,
                Value<int> rowid = const Value.absent(),
              }) => VerificationCacheCompanion.insert(
                uuid: uuid,
                chargeJson: chargeJson,
                terminee: terminee,
                rang: rang,
                soumiseLe: soumiseLe,
                fetchedAt: fetchedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $VerificationCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      VerificationCache,
      VerificationCacheData,
      $VerificationCacheFilterComposer,
      $VerificationCacheOrderingComposer,
      $VerificationCacheAnnotationComposer,
      $VerificationCacheCreateCompanionBuilder,
      $VerificationCacheUpdateCompanionBuilder,
      (
        VerificationCacheData,
        BaseReferences<_$AppDatabase, VerificationCache, VerificationCacheData>,
      ),
      VerificationCacheData,
      PrefetchHooks Function()
    >;
typedef $AnnuaireOutilsCacheCreateCompanionBuilder =
    AnnuaireOutilsCacheCompanion Function({
      Value<int> id,
      required String chargeJson,
      required DateTime fetchedAt,
    });
typedef $AnnuaireOutilsCacheUpdateCompanionBuilder =
    AnnuaireOutilsCacheCompanion Function({
      Value<int> id,
      Value<String> chargeJson,
      Value<DateTime> fetchedAt,
    });

class $AnnuaireOutilsCacheFilterComposer
    extends Composer<_$AppDatabase, AnnuaireOutilsCache> {
  $AnnuaireOutilsCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $AnnuaireOutilsCacheOrderingComposer
    extends Composer<_$AppDatabase, AnnuaireOutilsCache> {
  $AnnuaireOutilsCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $AnnuaireOutilsCacheAnnotationComposer
    extends Composer<_$AppDatabase, AnnuaireOutilsCache> {
  $AnnuaireOutilsCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get fetchedAt =>
      $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $AnnuaireOutilsCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          AnnuaireOutilsCache,
          AnnuaireOutilsCacheData,
          $AnnuaireOutilsCacheFilterComposer,
          $AnnuaireOutilsCacheOrderingComposer,
          $AnnuaireOutilsCacheAnnotationComposer,
          $AnnuaireOutilsCacheCreateCompanionBuilder,
          $AnnuaireOutilsCacheUpdateCompanionBuilder,
          (
            AnnuaireOutilsCacheData,
            BaseReferences<
              _$AppDatabase,
              AnnuaireOutilsCache,
              AnnuaireOutilsCacheData
            >,
          ),
          AnnuaireOutilsCacheData,
          PrefetchHooks Function()
        > {
  $AnnuaireOutilsCacheTableManager(_$AppDatabase db, AnnuaireOutilsCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $AnnuaireOutilsCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $AnnuaireOutilsCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $AnnuaireOutilsCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> chargeJson = const Value.absent(),
                Value<DateTime> fetchedAt = const Value.absent(),
              }) => AnnuaireOutilsCacheCompanion(
                id: id,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String chargeJson,
                required DateTime fetchedAt,
              }) => AnnuaireOutilsCacheCompanion.insert(
                id: id,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $AnnuaireOutilsCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      AnnuaireOutilsCache,
      AnnuaireOutilsCacheData,
      $AnnuaireOutilsCacheFilterComposer,
      $AnnuaireOutilsCacheOrderingComposer,
      $AnnuaireOutilsCacheAnnotationComposer,
      $AnnuaireOutilsCacheCreateCompanionBuilder,
      $AnnuaireOutilsCacheUpdateCompanionBuilder,
      (
        AnnuaireOutilsCacheData,
        BaseReferences<
          _$AppDatabase,
          AnnuaireOutilsCache,
          AnnuaireOutilsCacheData
        >,
      ),
      AnnuaireOutilsCacheData,
      PrefetchHooks Function()
    >;
typedef $TableauDeBordCacheCreateCompanionBuilder =
    TableauDeBordCacheCompanion Function({
      Value<int> id,
      required String chargeJson,
      required DateTime fetchedAt,
    });
typedef $TableauDeBordCacheUpdateCompanionBuilder =
    TableauDeBordCacheCompanion Function({
      Value<int> id,
      Value<String> chargeJson,
      Value<DateTime> fetchedAt,
    });

class $TableauDeBordCacheFilterComposer
    extends Composer<_$AppDatabase, TableauDeBordCache> {
  $TableauDeBordCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $TableauDeBordCacheOrderingComposer
    extends Composer<_$AppDatabase, TableauDeBordCache> {
  $TableauDeBordCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $TableauDeBordCacheAnnotationComposer
    extends Composer<_$AppDatabase, TableauDeBordCache> {
  $TableauDeBordCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get fetchedAt =>
      $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $TableauDeBordCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          TableauDeBordCache,
          TableauDeBordCacheData,
          $TableauDeBordCacheFilterComposer,
          $TableauDeBordCacheOrderingComposer,
          $TableauDeBordCacheAnnotationComposer,
          $TableauDeBordCacheCreateCompanionBuilder,
          $TableauDeBordCacheUpdateCompanionBuilder,
          (
            TableauDeBordCacheData,
            BaseReferences<
              _$AppDatabase,
              TableauDeBordCache,
              TableauDeBordCacheData
            >,
          ),
          TableauDeBordCacheData,
          PrefetchHooks Function()
        > {
  $TableauDeBordCacheTableManager(_$AppDatabase db, TableauDeBordCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $TableauDeBordCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $TableauDeBordCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $TableauDeBordCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> chargeJson = const Value.absent(),
                Value<DateTime> fetchedAt = const Value.absent(),
              }) => TableauDeBordCacheCompanion(
                id: id,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String chargeJson,
                required DateTime fetchedAt,
              }) => TableauDeBordCacheCompanion.insert(
                id: id,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $TableauDeBordCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      TableauDeBordCache,
      TableauDeBordCacheData,
      $TableauDeBordCacheFilterComposer,
      $TableauDeBordCacheOrderingComposer,
      $TableauDeBordCacheAnnotationComposer,
      $TableauDeBordCacheCreateCompanionBuilder,
      $TableauDeBordCacheUpdateCompanionBuilder,
      (
        TableauDeBordCacheData,
        BaseReferences<
          _$AppDatabase,
          TableauDeBordCache,
          TableauDeBordCacheData
        >,
      ),
      TableauDeBordCacheData,
      PrefetchHooks Function()
    >;
typedef $RepriseDeCoursCacheCreateCompanionBuilder =
    RepriseDeCoursCacheCompanion Function({
      Value<int> id,
      required bool presente,
      Value<String?> chargeJson,
      required DateTime fetchedAt,
    });
typedef $RepriseDeCoursCacheUpdateCompanionBuilder =
    RepriseDeCoursCacheCompanion Function({
      Value<int> id,
      Value<bool> presente,
      Value<String?> chargeJson,
      Value<DateTime> fetchedAt,
    });

class $RepriseDeCoursCacheFilterComposer
    extends Composer<_$AppDatabase, RepriseDeCoursCache> {
  $RepriseDeCoursCacheFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get presente => $composableBuilder(
    column: $table.presente,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $RepriseDeCoursCacheOrderingComposer
    extends Composer<_$AppDatabase, RepriseDeCoursCache> {
  $RepriseDeCoursCacheOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get presente => $composableBuilder(
    column: $table.presente,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $RepriseDeCoursCacheAnnotationComposer
    extends Composer<_$AppDatabase, RepriseDeCoursCache> {
  $RepriseDeCoursCacheAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<bool> get presente =>
      $composableBuilder(column: $table.presente, builder: (column) => column);

  GeneratedColumn<String> get chargeJson => $composableBuilder(
    column: $table.chargeJson,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get fetchedAt =>
      $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $RepriseDeCoursCacheTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          RepriseDeCoursCache,
          RepriseDeCoursCacheData,
          $RepriseDeCoursCacheFilterComposer,
          $RepriseDeCoursCacheOrderingComposer,
          $RepriseDeCoursCacheAnnotationComposer,
          $RepriseDeCoursCacheCreateCompanionBuilder,
          $RepriseDeCoursCacheUpdateCompanionBuilder,
          (
            RepriseDeCoursCacheData,
            BaseReferences<
              _$AppDatabase,
              RepriseDeCoursCache,
              RepriseDeCoursCacheData
            >,
          ),
          RepriseDeCoursCacheData,
          PrefetchHooks Function()
        > {
  $RepriseDeCoursCacheTableManager(_$AppDatabase db, RepriseDeCoursCache table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $RepriseDeCoursCacheFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $RepriseDeCoursCacheOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $RepriseDeCoursCacheAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<bool> presente = const Value.absent(),
                Value<String?> chargeJson = const Value.absent(),
                Value<DateTime> fetchedAt = const Value.absent(),
              }) => RepriseDeCoursCacheCompanion(
                id: id,
                presente: presente,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required bool presente,
                Value<String?> chargeJson = const Value.absent(),
                required DateTime fetchedAt,
              }) => RepriseDeCoursCacheCompanion.insert(
                id: id,
                presente: presente,
                chargeJson: chargeJson,
                fetchedAt: fetchedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $RepriseDeCoursCacheProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      RepriseDeCoursCache,
      RepriseDeCoursCacheData,
      $RepriseDeCoursCacheFilterComposer,
      $RepriseDeCoursCacheOrderingComposer,
      $RepriseDeCoursCacheAnnotationComposer,
      $RepriseDeCoursCacheCreateCompanionBuilder,
      $RepriseDeCoursCacheUpdateCompanionBuilder,
      (
        RepriseDeCoursCacheData,
        BaseReferences<
          _$AppDatabase,
          RepriseDeCoursCache,
          RepriseDeCoursCacheData
        >,
      ),
      RepriseDeCoursCacheData,
      PrefetchHooks Function()
    >;
typedef $$PendingOpsTableCreateCompanionBuilder =
    PendingOpsCompanion Function({
      required String clientUuid,
      required String op,
      required String endpoint,
      Value<String> method,
      required String payload,
      Value<String?> localFilePath,
      required DateTime createdAt,
      Value<int> attempts,
      Value<DateTime?> nextAttemptAt,
      Value<String?> lastError,
      Value<PendingOpStatus> status,
      Value<int> rowid,
    });
typedef $$PendingOpsTableUpdateCompanionBuilder =
    PendingOpsCompanion Function({
      Value<String> clientUuid,
      Value<String> op,
      Value<String> endpoint,
      Value<String> method,
      Value<String> payload,
      Value<String?> localFilePath,
      Value<DateTime> createdAt,
      Value<int> attempts,
      Value<DateTime?> nextAttemptAt,
      Value<String?> lastError,
      Value<PendingOpStatus> status,
      Value<int> rowid,
    });

class $$PendingOpsTableFilterComposer
    extends Composer<_$AppDatabase, $PendingOpsTable> {
  $$PendingOpsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get op => $composableBuilder(
    column: $table.op,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get method => $composableBuilder(
    column: $table.method,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localFilePath => $composableBuilder(
    column: $table.localFilePath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnFilters(column),
  );

  ColumnWithTypeConverterFilters<PendingOpStatus, PendingOpStatus, String>
  get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnWithTypeConverterFilters(column),
  );
}

class $$PendingOpsTableOrderingComposer
    extends Composer<_$AppDatabase, $PendingOpsTable> {
  $$PendingOpsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get op => $composableBuilder(
    column: $table.op,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get method => $composableBuilder(
    column: $table.method,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localFilePath => $composableBuilder(
    column: $table.localFilePath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PendingOpsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PendingOpsTable> {
  $$PendingOpsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => column,
  );

  GeneratedColumn<String> get op =>
      $composableBuilder(column: $table.op, builder: (column) => column);

  GeneratedColumn<String> get endpoint =>
      $composableBuilder(column: $table.endpoint, builder: (column) => column);

  GeneratedColumn<String> get method =>
      $composableBuilder(column: $table.method, builder: (column) => column);

  GeneratedColumn<String> get payload =>
      $composableBuilder(column: $table.payload, builder: (column) => column);

  GeneratedColumn<String> get localFilePath => $composableBuilder(
    column: $table.localFilePath,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lastError =>
      $composableBuilder(column: $table.lastError, builder: (column) => column);

  GeneratedColumnWithTypeConverter<PendingOpStatus, String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);
}

class $$PendingOpsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PendingOpsTable,
          PendingOpRow,
          $$PendingOpsTableFilterComposer,
          $$PendingOpsTableOrderingComposer,
          $$PendingOpsTableAnnotationComposer,
          $$PendingOpsTableCreateCompanionBuilder,
          $$PendingOpsTableUpdateCompanionBuilder,
          (
            PendingOpRow,
            BaseReferences<_$AppDatabase, $PendingOpsTable, PendingOpRow>,
          ),
          PendingOpRow,
          PrefetchHooks Function()
        > {
  $$PendingOpsTableTableManager(_$AppDatabase db, $PendingOpsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingOpsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingOpsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PendingOpsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> clientUuid = const Value.absent(),
                Value<String> op = const Value.absent(),
                Value<String> endpoint = const Value.absent(),
                Value<String> method = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<String?> localFilePath = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextAttemptAt = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                Value<PendingOpStatus> status = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingOpsCompanion(
                clientUuid: clientUuid,
                op: op,
                endpoint: endpoint,
                method: method,
                payload: payload,
                localFilePath: localFilePath,
                createdAt: createdAt,
                attempts: attempts,
                nextAttemptAt: nextAttemptAt,
                lastError: lastError,
                status: status,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String clientUuid,
                required String op,
                required String endpoint,
                Value<String> method = const Value.absent(),
                required String payload,
                Value<String?> localFilePath = const Value.absent(),
                required DateTime createdAt,
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextAttemptAt = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                Value<PendingOpStatus> status = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingOpsCompanion.insert(
                clientUuid: clientUuid,
                op: op,
                endpoint: endpoint,
                method: method,
                payload: payload,
                localFilePath: localFilePath,
                createdAt: createdAt,
                attempts: attempts,
                nextAttemptAt: nextAttemptAt,
                lastError: lastError,
                status: status,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PendingOpsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PendingOpsTable,
      PendingOpRow,
      $$PendingOpsTableFilterComposer,
      $$PendingOpsTableOrderingComposer,
      $$PendingOpsTableAnnotationComposer,
      $$PendingOpsTableCreateCompanionBuilder,
      $$PendingOpsTableUpdateCompanionBuilder,
      (
        PendingOpRow,
        BaseReferences<_$AppDatabase, $PendingOpsTable, PendingOpRow>,
      ),
      PendingOpRow,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $CompteCacheTableManager get compteCache =>
      $CompteCacheTableManager(_db, _db.compteCache);
  $CommunityCacheTableManager get communityCache =>
      $CommunityCacheTableManager(_db, _db.communityCache);
  $LmsCatalogueTableManager get lmsCatalogue =>
      $LmsCatalogueTableManager(_db, _db.lmsCatalogue);
  $LmsCoursTableManager get lmsCours =>
      $LmsCoursTableManager(_db, _db.lmsCours);
  $LmsLeconTableManager get lmsLecon =>
      $LmsLeconTableManager(_db, _db.lmsLecon);
  $LmsLectureActiveTableManager get lmsLectureActive =>
      $LmsLectureActiveTableManager(_db, _db.lmsLectureActive);
  $LmsCertificatTableManager get lmsCertificat =>
      $LmsCertificatTableManager(_db, _db.lmsCertificat);
  $LmsSeancesTableManager get lmsSeances =>
      $LmsSeancesTableManager(_db, _db.lmsSeances);
  $VerificationCacheTableManager get verificationCache =>
      $VerificationCacheTableManager(_db, _db.verificationCache);
  $AnnuaireOutilsCacheTableManager get annuaireOutilsCache =>
      $AnnuaireOutilsCacheTableManager(_db, _db.annuaireOutilsCache);
  $TableauDeBordCacheTableManager get tableauDeBordCache =>
      $TableauDeBordCacheTableManager(_db, _db.tableauDeBordCache);
  $RepriseDeCoursCacheTableManager get repriseDeCoursCache =>
      $RepriseDeCoursCacheTableManager(_db, _db.repriseDeCoursCache);
  $$PendingOpsTableTableManager get pendingOps =>
      $$PendingOpsTableTableManager(_db, _db.pendingOps);
}
