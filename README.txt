SIGEC — Système intégré de gestion des établissements carcéraux
================================================================

PRÉSENTATION
------------
SIGEC est une application web en français destinée à la gestion administrative et opérationnelle des établissements pénitentiaires. Elle regroupe les dossiers des personnes détenues, leur situation judiciaire et leur affectation, les visiteurs et visites, les stocks, la clinique, les incidents, les statistiques et le journal d’audit.

État du projet : application en cours de développement. Ce document décrit les fonctions présentes dans le code à la date de sa dernière mise à jour. Certaines procédures administratives et mesures de sécurité restent à valider avant une utilisation réelle.

OBJECTIFS ET POINTS FORTS
------------------------
- Centraliser les informations de plusieurs établissements et limiter chaque accès selon le rôle et l’établissement attribués.
- Représenter la structure réelle d’un établissement : un ou plusieurs bâtiments et les cellules de chacun. Un établissement peut aussi ne comporter qu’une cellule.
- Suivre les admissions, les séjours, les mouvements, les libérations, les visites, les soins, les incidents et les mouvements de stock.
- Calculer l’occupation par établissement et par cellule, afficher les situations de surpopulation et les consigner dans le journal d’audit.
- Saisir certaines opérations sans réseau sur un appareil, puis les synchroniser avec la base principale au retour de la connexion.
- Garder un historique consultable des opérations sensibles et des connexions à l’application.

MODULES ET PARCOURS MÉTIER
-------------------------

1. Établissements et structure
L’administration centrale crée et met à jour les établissements. Elle peut ensuite enregistrer leurs bâtiments et cellules. Une cellule possède une capacité de référence; cette valeur sert au calcul de l’occupation, mais ne bloque pas une admission en dépassement. La surpopulation est permise et signalée dans les statistiques et le journal d’audit.

2. Personnes détenues
Le dossier comprend un numéro de dossier dont les pages détenu affichent le préfixe DET, le nom, le post-nom facultatif et le prénom, la date de naissance facultative, le sexe, une photo, des informations biométriques, les dossiers judiciaires, les ordres de détention, les séjours et l’historique des affectations en cellule. L’admission en ligne crée le dossier, le séjour et l’affectation en cellule. Les transferts et libérations sont enregistrés séparément. La direction, le service judiciaire et l’administration centrale peuvent corriger l’identité; chaque correction est auditée sans recopier les données personnelles dans le journal.

Une pré-admission hors connexion reste un brouillon. Après synchronisation, un utilisateur habilité de la direction ou du service judiciaire, ou l’administration centrale, doit examiner l’ordre. L’approbation crée alors l’admission officielle; le rejet exige un motif.

3. Magistrats et décisions judiciaires
L’administration centrale gère le répertoire des magistrats. Les décisions de détention sont liées au dossier judiciaire; au moment de l’admission, le magistrat peut être associé à l’ordre.

4. Visiteurs et visites
Les fiches de détenus, visiteurs et magistrats distinguent le nom, le post-nom facultatif et le prénom. Pour une visite, l’agent choisit d’abord le détenu, saisit l’identité du visiteur, puis enregistre son arrivée; l’heure est horodatée et la visite passe au statut « arrivée ». À la sortie, l’agent enregistre la fin de visite; le journal conserve l’heure de sortie et le statut « terminée ». Le registre par jour et le dossier du détenu montrent les visites.

5. Stocks
L’administration centrale gère le catalogue commun des articles. Dans chaque établissement, les agents autorisés enregistrent les entrées et sorties par emplacement, suivent les quantités et seuils de réapprovisionnement. Une sortie supérieure au stock disponible est refusée.

6. Clinique
Les utilisateurs habilités consignent les consultations, motifs, observations, soins et orientations. Le détenu doit avoir un séjour actif dans l’établissement concerné.

7. Incidents
Les utilisateurs autorisés signalent les incidents, leur type, leur gravité, leur date, leur lieu et les premières mesures prises, puis suivent leur traitement jusqu’à la clôture.

8. Tableau de bord et rapports
Le tableau de bord présente les établissements accessibles, les personnes détenues, les bâtiments, les cellules, les capacités, l’occupation, les incidents ouverts, les consultations du mois, les visites du jour et les cellules en surpopulation. Le rapport d’occupation peut être filtré et exporté en CSV.

9. Journal d’audit et connexions
La table audit_logs enregistre les opérations métier, ainsi que les connexions réussies, les déconnexions et les tentatives de connexion échouées. Les événements d’authentification incluent la date, l’adresse IP et le navigateur; pour un échec, l’identifiant de connexion saisi peut aussi être consigné. Les mots de passe ne sont jamais enregistrés. L’administration centrale peut consulter et filtrer le journal, puis exporter les résultats en CSV.

Les nouvelles connexions sont enregistrées à partir de l’activation de ce suivi; les connexions antérieures ne peuvent pas être reconstituées rétroactivement.

GESTION DES ACCÈS
-----------------
Les comptes sont activés ou désactivés par l’administration centrale, qui attribue les accès par établissement. Les rôles disponibles dans le code sont :

- Administration centrale : accès transversal, création des établissements et comptes, gestion du catalogue de stock, répertoire des magistrats, statistiques globales et audit.
- Direction : gestion des opérations permises dans les établissements affectés, dont la structure, les admissions et leur examen.
- Agent : opérations autorisées sur les détenus, les visites, les incidents et les stocks selon le module.
- Judiciaire : consultation et opérations judiciaires prévues, examen des pré-admissions et décisions de détention.
- Garde / custody : visites, mouvements et opérations de détention prévus par les contrôles d’accès.
- Médical : consultations cliniques dans les établissements attribués.

Un utilisateur opérationnel ne conserve qu’un seul accès d’établissement actif à la fois. Lorsqu’un administrateur central lui attribue un nouvel établissement, l’accès aux précédents est désactivé; les anciennes associations restent visibles comme historique. Les autorisations serveur s’appuient sur cet accès actif pour cloisonner les données. Les brouillons chiffrés déjà enregistrés hors connexion dans un navigateur ne sont pas supprimés automatiquement par ce changement.

Les contrôles d’accès sont appliqués côté serveur. Cacher un bouton dans l’interface ne remplace pas ces vérifications.

FONCTIONNEMENT HORS CONNEXION
----------------------------
La page /offline-visits.html est une interface de saisie hors connexion mise en cache par le service worker. Elle permet de conserver localement les arrivées de visiteurs, pré-admissions, mouvements de détenus, consultations, incidents et mouvements de stock. La file locale est chiffrée dans le navigateur avec une phrase secrète d’au moins 8 caractères; cette phrase ne peut pas être récupérée par le serveur.

Quand le réseau revient, l’utilisateur se connecte à SIGEC puis synchronise la file. Le serveur vérifie les droits, les codes et les règles métier. Une pré-admission synchronisée reste en attente d’examen; elle n’ajoute pas immédiatement une personne au registre officiel.

Le mode hors connexion ne rend pas toute l’application disponible hors réseau : il concerne les formulaires listés ci-dessus. La gestion des comptes, les recherches dans les registres, les examens, les approbations et la consultation centrale nécessitent une connexion au serveur. Les saisies restent d’abord sur l’appareil et ne sont pas visibles sur les autres appareils avant synchronisation.

DONNÉES PRINCIPALES
-------------------
Les migrations définissent notamment les tables suivantes :

Comptes et autorisations : users, sessions, establishment_user_access.
Établissements et hébergement : establishments, establishment_buildings, cells, cell_assignments.
Détention et justice : detainees, detainee_biometrics, detention_cases, detention_orders, detention_stays, sentence_adjustments, magistrates.
Visites : visitors, prison_visits.
Stocks : inventory_items, establishment_stocks, stock_movements.
Santé et incidents : medical_consultations, incidents, incident_participants, incident_actions.
Audit et saisies différées : audit_logs, detainee_admission_drafts.

audit_logs est le journal principal des actions. sessions est utilisé pour les sessions applicatives et ne remplace pas l’historique durable du journal d’audit. Les pré-admissions en attente sont conservées dans detainee_admission_drafts; leur contenu est chiffré côté application avec la clé définie dans APP_KEY.

TECHNOLOGIES
------------
- PHP 8.3 ou supérieur et Laravel 13.
- React 19, TypeScript et Inertia.js.
- Tailwind CSS 4 et Vite Plus pour l’interface.
- SQLite configuré par défaut dans l’environnement local; Laravel peut être configuré pour un autre moteur pris en charge.
- Authentification Laravel Fortify, avec prise en charge de l’authentification à deux facteurs et des passkeys déjà présente dans le projet.

INSTALLATION ET LANCEMENT LOCAL SOUS WINDOWS
--------------------------------------------
Dans PowerShell, placez-vous dans le dossier du projet (C:\Dev\sigec). Si PowerShell bloque le script npm.ps1, utilisez npm.cmd.

    composer install
    Copy-Item .env.example .env  # seulement si .env n’existe pas encore
    php artisan key:generate     # uniquement à la première installation
    npm.cmd install
    php artisan migrate
    npm.cmd run build

Pour le développement, ouvrez deux fenêtres PowerShell dans le projet.

Fenêtre 1 : serveur Laravel
    php artisan serve --host=127.0.0.1 --port=8000

Fenêtre 2 : serveur Vite
    npm.cmd run dev

Accédez ensuite à http://127.0.0.1:8000. Si une autre instance utilise déjà le port, choisissez un port libre et utilisez la même adresse pour Vite et le navigateur.

L’environnement de cette installation utilise SQLite (DB_CONNECTION=sqlite) et les migrations placent le fichier local dans database/database.sqlite. Pour un déploiement partagé, configurez les identifiants du moteur de base de données dans .env afin que tous les serveurs de l’application utilisent la même base principale. Ne partagez pas un fichier SQLite par synchronisation de fichiers entre plusieurs serveurs.

Premier compte administrateur
-----------------------------
1. Créez un compte depuis la page d’inscription de l’application.
2. Depuis le dossier du projet, accordez le rôle central à son adresse :

    php artisan sigec:grant-access administrateur@example.com central_admin

Remplacez l’adresse par celle du compte existant. Les comptes des agents et leurs établissements se gèrent ensuite dans le module Utilisateurs. Un rôle local peut aussi être accordé par la commande en précisant --establishment=ID.

MODIFICATIONS DE LA BASE
------------------------
Les évolutions du schéma doivent être ajoutées sous forme de nouvelles migrations, puis appliquées avec :

    php artisan migrate

Évitez migrate:fresh et db:wipe sur une base qui contient des données : ces commandes effacent les tables. Sauvegardez la base avant toute opération de maintenance.

VÉRIFICATIONS AUTOMATISÉES
--------------------------
La suite PHPUnit est configurée avec SQLite en mémoire (phpunit.xml); elle ne modifie pas la base locale database/database.sqlite. Les tests couvrent les parcours de transfert entre cellules, admission différée et approbation, visiteur puis programmation d’une visite, arrivées et incidents hors connexion, suivi/résolution des incidents, stock et refus d’une sortie supérieure au stock, consultation médicale et journalisation sans notes médicales.

La matrice des accès vérifie les profils direction, agent, judiciaire, garde et médical, ainsi que l’administrateur central. Les tests vérifient aussi le cloisonnement de certains accès entre établissements. Ils constituent une vérification HTTP automatisée; ils ne remplacent pas un contrôle visuel manuel dans le navigateur ni une recette avec les utilisateurs métier.

SÉCURITÉ ET LIMITES À TRAITER AVANT UN DÉPLOIEMENT RÉEL
-------------------------------------------------------
- Utiliser HTTPS, protéger APP_KEY, limiter les accès serveur et mettre en place des sauvegardes vérifiées de la base principale.
- La recette du 2026-10-21 a confirmé l’accès au formulaire hors connexion. Après avoir remplacé le stockage local des sessions et du cache par des fichiers, /login et /register s’ouvrent dans le navigateur; l’inscription et les autres écrans connectés restent à valider avec un compte de test.
- Le .env actuel est configuré pour le développement local (APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8000, SQLite, sessions et cache sur fichiers). Il ne doit pas être réutilisé en production. Les exigences de configuration, sauvegarde et recette sont décrites dans DEPLOIEMENT.txt.
- Aucun fichier de sauvegarde distinct n’a été repéré à côté de la base locale pendant la vérification. Une stratégie de sauvegarde et une restauration testée sont requises avant mise en production.
- L’inscription Fortify est actuellement activée; la politique d’ouverture/approbation des comptes reste à confirmer avant exposition publique.
- Audit des dépendances du 2026-10-21 : Composer et les dépendances npm de production n’ont signalé aucune vulnérabilité. Les deux alertes critiques de tinypool ont été traitées par un verrouillage à la version corrigée 2.1.2. L’audit npm complet signale encore une alerte élevée liée à vite-plus 0.3.0/launch-editor sous Windows; la migration vers Vite Plus 1.3.0 reste à faire.
- Un contrôle SQLite en lecture seule a donné integrity_check=ok. La base n’a pas été modifiée par cette vérification.
- Définir les obligations de conservation, d’accès et de traitement des données personnelles et judiciaires avec les responsables compétents.
- Les gabarits d’empreinte sont stockés dans encrypted_template. Le parcours d’admission transmet le contenu binaire brut et le modèle DetaineeBiometric le chiffre au repos avec le cast encrypted de Laravel, dérivé de APP_KEY. Base64 n’est pas utilisé comme mécanisme de protection. Toute perte ou rotation non planifiée de APP_KEY rendrait les gabarits existants illisibles. Les règles de collecte, d’accès, de conservation et de suppression doivent être validées avant usage réel.
- Une pré-admission hors connexion n’inclut pas de photo ni d’empreinte; ces éléments doivent être recueillis dans le parcours en ligne autorisé.
- L’audit des connexions est append-only via le modèle d’audit, mais les exigences de rétention, d’export et de supervision doivent être définies pour l’exploitation.
- L’application n’a pas encore de synchronisation complète de toute son interface et de tous ses registres hors connexion.

POLITIQUE DE MISE À JOUR DE CE README
-------------------------------------
À chaque modification fonctionnelle ou technique du projet, ce fichier doit être mis à jour dans la même opération lorsque cette modification change les modules, parcours, accès, données, installation, commandes, fonctionnement hors connexion ou limites connues. Les descriptions doivent rester conformes au code livré; les fonctions prévues mais absentes doivent être présentées comme travaux restants, jamais comme des capacités déjà disponibles.

HISTORIQUE
----------
2026-10-30 — Retrait de la connexion par clé d’accès de la page de connexion; l’authentification se fait par adresse e-mail et mot de passe. La gestion et la confirmation par clé d’accès dans les autres parcours restent disponibles.
2026-10-29 — La liste des personnes détenues distingue les séjours actifs des dossiers libérés. La libération clôt l’affectation à la cellule; le dossier et son historique restent accessibles dans l’onglet Libérés. Le parcours de réadmission réutilise le dossier existant et ajoute un nouveau séjour avec un nouveau titre légal. Le formulaire de libération affiche ses champs clairement, précise les erreurs sous chaque champ, permet de détailler un motif « Autre » et impose une date strictement postérieure à l’admission et inférieure ou égale à maintenant. La date locale est convertie en UTC avant envoi pour éviter les rejets causés par le fuseau horaire du navigateur.
2026-10-29 — Dans les visites, les sélecteurs et journaux affichent le nom, le post-nom et le prénom du détenu sans afficher son numéro de dossier; la recherche accepte toujours ce numéro.
2026-10-27 — Le changement d’établissement d’un utilisateur désactive ses anciens accès actifs. Ajout de la modification des noms de bâtiments et cellules. Après inscription, redirection vers le tableau de bord SIGEC plutôt que vers le tableau de bord générique de démonstration; libellés d’établissement précisés en français. Ces changements restent à publier sur Render.
2026-10-27 — Le parcours des visites en ligne enregistre désormais l’arrivée à la saisie du visiteur; la sortie termine la visite et est horodatée depuis le journal. Le mode hors connexion utilise déjà le statut « arrivée » lors de la synchronisation.
2026-10-21 — Recette navigateur : page hors connexion et connexion/inscription accessibles; remplacement des sessions et du cache SQLite locaux par le stockage fichier après un dépassement de délai. Traduction française des pages d’authentification. Ajout de DEPLOIEMENT.txt avec les constats et exigences; aucun déploiement ni migration de production effectué.
2026-10-21 — Préparation sécurité : mise à jour verrouillée de tinypool en 2.1.2; audits Composer et npm production sans vulnérabilité. Alerte élevée persistante sur vite-plus 0.3.0; migration officielle non exécutée car son binding natif Windows temporaire était invalide. Compilation et 104 tests réussis.
2026-10-21 — Vérification des parcours métier et des droits par tests automatisés. Correction du parcours de programmation d’une visite avec un visiteur déjà enregistré; ajout des tests de la matrice de rôles et des flux stock, visite, incident et pré-admission hors connexion.
2026-10-21 — Ajout du post-nom facultatif aux fiches des détenus, visiteurs et magistrats, avec conservation des dossiers existants; ajout de la correction d’identité détenu contrôlée par rôle et journalisée.
2026-10-19 — Première version : présentation de SIGEC, des modules métier, des rôles, de la base principale, du mode hors connexion, du lancement local et des limites de sécurité connues.

DÉPLOIEMENT DE TEST
-------------------
Render est utilisé pour le service de test sigec-test, connecté au dépôt GitHub privé du projet; les changements poussés sur main déclenchent le déploiement. La préparation Docker est présente dans Dockerfile, .dockerignore et start-render.sh. Le service gratuit s'endort à l'inactivité, sa base gratuite expire après 30 jours et ses fichiers téléversés ne sont pas durables. Ce plan ne convient pas aux données réelles.
