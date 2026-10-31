<?php

namespace Database\Seeders;

use App\Enums\AccountPotential;
use App\Enums\AccountStatus;
use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Enums\CrmTagColor;
use App\Enums\FollowUpReminderStage;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Enums\QuoteKind;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\CalendarEvent;
use App\Models\ContactRequest;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmActivityAttachment;
use App\Models\CrmContact;
use App\Models\CrmFollowUpReminder;
use App\Models\CrmOpportunity;
use App\Models\CrmTag;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestFile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Jeu de données de démonstration pour tester l'application à la main.
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Le seeder est idempotent : les enregistrements sont recherchés par une clé
 * stable puis mis à jour, jamais dupliqués. Aucune donnée existante n'est
 * supprimée.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Photo JPEG 1 × 1 pixel, suffisante pour tester la galerie et les
     * téléchargements sans dépendre de l'extension GD.
     */
    private const DEMO_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==';

    /**
     * Plan PDF minimal joint aux demandes de devis de démonstration.
     */
    private const DEMO_PDF = <<<'PDF'
%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj
4 0 obj << /Length 66 >> stream
BT /F1 18 Tf 72 760 Td (Plan de demonstration C2ME) Tj ET
endstream endobj
5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj
trailer << /Root 1 0 R >>
%%EOF
PDF;

    public function run(): void
    {
        $admin = $this->adminUser();
        $commercial = $this->demoUser('demo.commercial@c2me.fr', 'Camille Commercial', UserRole::Commercial, 'Chargée d’affaires');
        $technician = $this->demoUser('demo.be@c2me.fr', 'Sacha Bureau d’études', UserRole::Commercial, 'Chargé d’études');

        $mailbox = $this->mailbox($commercial);
        $this->seedTags();

        // -----------------------------------------------------------------
        // Carnet 1 — Prescripteurs
        // -----------------------------------------------------------------

        $aria = $this->account($commercial, [
            'name' => 'Atelier Aria Architectes',
            'book' => CrmBook::Prescribers,
            'category' => CrmCategory::Architects,
            'status' => AccountStatus::Client,
            'potential' => AccountPotential::A,
            'source' => CrmSource::Referral,
            'email' => 'contact@atelier-aria.test',
            'phone' => '02 40 12 34 56',
            'address_line1' => '12 rue des Olivettes',
            'postal_code' => '44000',
            'city' => 'Nantes',
            'metadata' => [
                'specialty' => 'Architecture tertiaire et équipements publics',
                'intervention_area' => 'Loire-Atlantique, Vendée',
                'referrer' => 'Cabinet Lemoine',
                'commission_rate' => 3,
                'missions' => 'Études de faisabilité, permis de construire, suivi de chantier.',
            ],
            'notes' => 'Prescripteur historique : privilégie les réponses rapides et les plans techniques détaillés.',
            'last_contacted_at' => now()->subDays(6),
            'next_follow_up_at' => now()->addDays(3)->setTime(9, 0),
        ], ['Grand compte', 'Partenaire']);

        $claire = $this->contact($aria, 'Claire', 'Rousseau', 'claire.rousseau@atelier-aria.test', 'Architecte associée', true);
        $this->contact($aria, 'Thomas', 'Garnier', 'thomas.garnier@atelier-aria.test', 'Chef de projet');

        $ariaOpportunity = $this->opportunity($aria, $claire, $commercial, 'Extension du siège — 450 m²', OpportunityStage::Negotiation, 185000);
        $this->seedTimeline($aria, $claire, $ariaOpportunity, $commercial, 'extension du siège Aria');

        $structura = $this->account($commercial, [
            'name' => 'BET Structura Ingénierie',
            'book' => CrmBook::Prescribers,
            'category' => CrmCategory::EngineeringOffices,
            'status' => AccountStatus::Prospect,
            'potential' => AccountPotential::B,
            'source' => CrmSource::Website,
            'email' => 'contact@bet-structura.test',
            'phone' => '02 99 45 67 89',
            'address_line1' => '4 avenue de la Gare',
            'postal_code' => '35000',
            'city' => 'Rennes',
            'metadata' => [
                'specialty' => 'Structures métalliques et bois',
                'intervention_area' => 'Bretagne, Pays de la Loire',
                'referrer' => null,
                'commission_rate' => 0,
                'missions' => 'Notes de calcul, plans d’exécution, assistance technique.',
            ],
            'notes' => 'Premier contact via le formulaire du site : attend un devis pour un hangar logistique.',
            'last_contacted_at' => now()->subDays(2),
            'next_follow_up_at' => now()->addDays(5)->setTime(9, 0),
        ], ['À relancer']);

        $lucas = $this->contact($structura, 'Lucas', 'Perrin', 'lucas.perrin@bet-structura.test', 'Ingénieur structure', true);
        $this->contact($structura, 'Inès', 'Bonnet', 'ines.bonnet@bet-structura.test', 'Assistante d’études');

        $structuraOpportunity = $this->opportunity($structura, $lucas, $commercial, 'Étude charpente — hangar logistique', OpportunityStage::Study, 42000);
        $this->seedTimeline($structura, $lucas, $structuraOpportunity, $commercial, 'hangar logistique Structura');

        // -----------------------------------------------------------------
        // Carnet 2 — Clients industriels
        // -----------------------------------------------------------------

        $derval = $this->account($commercial, [
            'name' => 'Mécanique Derval',
            'book' => CrmBook::IndustrialClients,
            'category' => CrmCategory::Factories,
            'status' => AccountStatus::Client,
            'potential' => AccountPotential::A,
            'source' => CrmSource::Prospecting,
            'email' => 'achats@mecanique-derval.test',
            'phone' => '02 41 78 90 12',
            'address_line1' => 'Zone industrielle du Chêne',
            'postal_code' => '49300',
            'city' => 'Cholet',
            'metadata' => [
                'sites' => 'Cholet (siège), Mortagne-sur-Sèvre',
                'industry' => 'Mécanique de précision',
                'headcount' => 85,
                'maintenance_mode' => 'mixte',
                'shutdown_frequency' => 'Deux arrêts par an',
                'equipment' => 'Deux lignes de découpe, une presse plieuse.',
            ],
            'notes' => 'Commande régulière de pièces laser. Contact achats réactif, valide les devis sous 8 jours.',
            'last_contacted_at' => now()->subDays(4),
            'next_follow_up_at' => now()->addDays(1)->setTime(9, 0),
        ], ['Chaud', 'Grand compte']);

        $nadia = $this->contact($derval, 'Nadia', 'Lebreton', 'nadia.lebreton@mecanique-derval.test', 'Responsable achats', true);
        $this->contact($derval, 'Hugo', 'Marchand', 'hugo.marchand@mecanique-derval.test', 'Responsable maintenance');

        $dervalOpportunity = $this->opportunity($derval, $nadia, $commercial, 'Ligne de découpe laser — pièces acier', OpportunityStage::QuoteSent, 26700);
        $this->seedTimeline($derval, $nadia, $dervalOpportunity, $commercial, 'pièces laser Derval');

        $agro = $this->account($technician, [
            'name' => 'Agro Laitière du Bocage',
            'book' => CrmBook::IndustrialClients,
            'category' => CrmCategory::FoodIndustry,
            'status' => AccountStatus::Prospect,
            'potential' => AccountPotential::B,
            'source' => CrmSource::TradeShow,
            'email' => 'projets@agro-bocage.test',
            'phone' => '02 51 23 45 67',
            'address_line1' => '7 route de la Fromagerie',
            'postal_code' => '85000',
            'city' => 'La Roche-sur-Yon',
            'metadata' => [
                'sites' => 'La Roche-sur-Yon',
                'industry' => 'Agroalimentaire',
                'headcount' => 140,
                'maintenance_mode' => 'interne',
                'shutdown_frequency' => 'Arrêt estival de trois semaines',
                'equipment' => 'Lignes de conditionnement, convoyeurs inox.',
            ],
            'notes' => 'Rencontré au salon Eurobois. Projet d’extension de la fromagerie à l’étude.',
            'last_contacted_at' => now()->subDays(9),
            'next_follow_up_at' => now()->addDays(7)->setTime(9, 0),
        ], ['Partenaire']);

        $pierre = $this->contact($agro, 'Pierre', 'Chauvet', 'pierre.chauvet@agro-bocage.test', 'Directeur industriel', true);
        $this->contact($agro, 'Élodie', 'Roux', 'elodie.roux@agro-bocage.test', 'Responsable maintenance');

        $agroOpportunity = $this->opportunity($agro, $pierre, $technician, 'Extension fromagerie — 900 m²', OpportunityStage::Won, 152000);
        $this->seedTimeline($agro, $pierre, $agroOpportunity, $technician, 'extension fromagerie');

        // -----------------------------------------------------------------
        // Carnet 3 — Entreprises du bâtiment
        // -----------------------------------------------------------------

        $charpente = $this->account($technician, [
            'name' => 'Charpente & Toiture Maine',
            'book' => CrmBook::BuildingCompanies,
            'category' => CrmCategory::RoofersCladders,
            'status' => AccountStatus::Client,
            'potential' => AccountPotential::A,
            'source' => CrmSource::WordOfMouth,
            'email' => 'contact@charpente-maine.test',
            'phone' => '02 43 56 78 90',
            'address_line1' => '18 rue des Artisans',
            'postal_code' => '72000',
            'city' => 'Le Mans',
            'metadata' => [
                'trades' => 'Couverture, bardage, charpente métallique',
                'company_size' => 'PME — 24 salariés',
                'intervention_area' => 'Sarthe, Mayenne',
                'site_types' => 'Bâtiments industriels, hangars agricoles, équipements publics.',
                'qualifications' => 'Qualibat 2311, RGE',
                'insurance' => 'Décennale AXA — échéance 2027',
            ],
            'notes' => 'Partenaire de longue date. Demande systématiquement une visite avant chiffrage.',
            'last_contacted_at' => now()->subDays(3),
            'next_follow_up_at' => now()->addDays(1)->setTime(9, 0),
        ], ['Grand compte', 'Chantier']);

        $julien = $this->contact($charpente, 'Julien', 'Bodin', 'julien.bodin@charpente-maine.test', 'Gérant', true);
        $this->contact($charpente, 'Sophie', 'Guerin', 'sophie.guerin@charpente-maine.test', 'Conductrice de travaux');

        $charpenteOpportunity = $this->opportunity($charpente, $julien, $technician, 'Couverture bâtiment industriel — 1 200 m²', OpportunityStage::Lost, 96000);
        $charpenteVisit = $this->seedTimeline($charpente, $julien, $charpenteOpportunity, $technician, 'couverture industrielle');

        // Photos de chantier : visibles dans le back-office et le mode terrain.
        $this->attachPhoto($charpenteVisit, 'chantier-maine-1.jpg', '11111111-1111-4111-8111-111111111111');
        $this->attachPhoto($charpenteVisit, 'chantier-maine-2.jpg', '22222222-2222-4222-8222-222222222222');

        $belliard = $this->account($technician, [
            'name' => 'Constructions Belliard',
            'book' => CrmBook::BuildingCompanies,
            'category' => CrmCategory::GeneralContractors,
            'status' => AccountStatus::Prospect,
            'potential' => AccountPotential::C,
            'source' => CrmSource::Network,
            'email' => 'etudes@constructions-belliard.test',
            'phone' => '02 41 90 12 34',
            'address_line1' => '32 boulevard Foch',
            'postal_code' => '49000',
            'city' => 'Angers',
            'metadata' => [
                'trades' => 'Gros œuvre, ossature métallique',
                'company_size' => 'ETI — 120 salariés',
                'intervention_area' => 'Grand Ouest',
                'site_types' => 'Bâtiments tertiaires, plateformes logistiques.',
                'qualifications' => 'Qualibat 1512, MASE',
                'insurance' => 'Décennale SMA — échéance 2028',
            ],
            'notes' => 'Dossier en attente de décision budgétaire. Relancer après la clôture trimestrielle.',
            'last_contacted_at' => now()->subDays(21),
            'next_follow_up_at' => now()->addDays(12)->setTime(9, 0),
        ], ['À relancer']);

        $marc = $this->contact($belliard, 'Marc', 'Belliard', 'marc.belliard@constructions-belliard.test', 'Président', true);
        $this->contact($belliard, 'Julie', 'Fontaine', 'julie.fontaine@constructions-belliard.test', 'Responsable études');

        $belliardOpportunity = $this->opportunity($belliard, $marc, $technician, 'Ossature métallique — 800 m²', OpportunityStage::Qualified, 74000);
        $this->seedTimeline($belliard, $marc, $belliardOpportunity, $technician, 'ossature 800 m²', 0);

        $fermes = $this->account($commercial, [
            'name' => 'Fermes Toitures 49',
            'book' => CrmBook::BuildingCompanies,
            'category' => CrmCategory::Farmers,
            'status' => AccountStatus::Partner,
            'potential' => AccountPotential::B,
            'source' => CrmSource::Referral,
            'email' => 'contact@fermes-toitures.test',
            'phone' => '02 41 33 44 55',
            'address_line1' => 'Lieu-dit La Blanchardière',
            'postal_code' => '49220',
            'city' => 'Grez-Neuville',
            'metadata' => [
                'trades' => 'Charpente, couverture, bâtiments agricoles',
                'company_size' => 'Artisan — 6 salariés',
                'intervention_area' => 'Anjou, Segréen',
                'site_types' => 'Hangars agricoles, stabulations, stockage.',
                'qualifications' => 'Qualibat 2311',
                'insurance' => 'Décennale Groupama — échéance 2027',
            ],
            'notes' => 'Apporte régulièrement des affaires. Prévoir un devis type pour les hangars 1 000 m².',
            'last_contacted_at' => now()->subDays(11),
            'next_follow_up_at' => now()->addDays(15)->setTime(9, 0),
        ], ['Partenaire', 'Chantier']);

        $didier = $this->contact($fermes, 'Didier', 'Moreau', 'didier.moreau@fermes-toitures.test', 'Gérant', true);
        $this->contact($fermes, 'Karine', 'Leroy', 'karine.leroy@fermes-toitures.test', 'Assistante de gestion');

        $fermesOpportunity = $this->opportunity($fermes, $didier, $commercial, 'Hangar agricole — 1 200 m²', OpportunityStage::Negotiation, 118000);
        $this->seedTimeline($fermes, $didier, $fermesOpportunity, $commercial, 'hangar agricole 1 200 m²');

        $metal = $this->account($admin, [
            'name' => 'Atelier du Métal Nantais',
            'book' => CrmBook::BuildingCompanies,
            'category' => CrmCategory::Builders,
            'status' => AccountStatus::Inactive,
            'potential' => AccountPotential::C,
            'source' => CrmSource::Other,
            'email' => 'contact@atelier-metal-nantais.test',
            'phone' => '02 40 55 66 77',
            'address_line1' => '5 impasse des Forges',
            'postal_code' => '44400',
            'city' => 'Rezé',
            'metadata' => [
                'trades' => 'Serrurerie, structure métallique',
                'company_size' => 'TPE — 4 salariés',
                'intervention_area' => 'Nantes métropole',
                'site_types' => 'Rénovation, petites extensions.',
                'qualifications' => 'Aucune',
                'insurance' => 'Décennale non renouvelée',
            ],
            'notes' => 'Compte inactif depuis la reprise de l’atelier. Garder pour historique.',
            'last_contacted_at' => now()->subMonths(8),
            'next_follow_up_at' => null,
        ], []);

        $yann = $this->contact($metal, 'Yann', 'Dubois', 'yann.dubois@atelier-metal-nantais.test', 'Ancien gérant', true);
        $metalOpportunity = $this->opportunity($metal, $yann, $admin, 'Reprise de l’atelier', OpportunityStage::New, 25000);
        $this->seedTimeline($metal, $yann, $metalOpportunity, $admin, 'reprise d’atelier', 0);

        // -----------------------------------------------------------------
        // Devis (tous les statuts documentaires)
        // -----------------------------------------------------------------

        $this->quote($structura, $lucas, $structuraOpportunity, $commercial, 'Étude et fabrication — hangar logistique', QuoteDocumentStatus::Draft, [
            ['designation' => 'Étude d’exécution charpente métallique', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 4200],
            ['designation' => 'Fourniture et fabrication — portiques S235', 'quantity' => 18, 'unit' => 't', 'unit_price' => 1450],
            ['designation' => 'Plans de montage et notice', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 650],
        ]);

        $this->quote($derval, $nadia, $dervalOpportunity, $commercial, 'Découpe laser — pièces acier 3 mm', QuoteDocumentStatus::Sent, [
            ['designation' => 'Découpe laser acier S235 — 3 mm', 'quantity' => 240, 'unit' => 'u', 'unit_price' => 18.5],
            ['designation' => 'Pliage et finition', 'quantity' => 240, 'unit' => 'u', 'unit_price' => 6.2],
            ['designation' => 'Livraison Cholet', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 180],
        ]);

        $this->quote($agro, $pierre, $agroOpportunity, $technician, 'Extension fromagerie — charpente et bardage', QuoteDocumentStatus::Accepted, [
            ['designation' => 'Charpente métallique — portique 12 m', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 86000],
            ['designation' => 'Bardage double peau avec isolation', 'quantity' => 540, 'unit' => 'm²', 'unit_price' => 92],
            ['designation' => 'Pose, calage et étanchéité', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 21500],
        ]);

        $this->quote($charpente, $julien, $charpenteOpportunity, $technician, 'Couverture bâtiment industriel — 1 200 m²', QuoteDocumentStatus::Refused, [
            ['designation' => 'Couverture bac acier isolée', 'quantity' => 1200, 'unit' => 'm²', 'unit_price' => 58],
            ['designation' => 'Évacuation des eaux pluviales', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 7400],
        ]);

        $this->quote($belliard, $marc, $belliardOpportunity, $technician, 'Ossature métallique — 800 m²', QuoteDocumentStatus::Expired, [
            ['designation' => 'Ossature métallique — poteaux et poutres', 'quantity' => 34, 'unit' => 't', 'unit_price' => 1650],
            ['designation' => 'Ancrages et platines', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 5200],
        ]);

        $this->quote($metal, $yann, $metalOpportunity, $admin, 'Reprise de l’atelier — reprise de structure', QuoteDocumentStatus::Cancelled, [
            ['designation' => 'Diagnostic de structure existante', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => 1800],
        ]);

        // -----------------------------------------------------------------
        // Relances programmées
        // -----------------------------------------------------------------

        $this->followUp($derval, $commercial, FollowUpReminderStage::DayBefore, now()->addDay()->setTime(9, 0), false);
        $this->followUp($charpente, $technician, FollowUpReminderStage::DueDay, now()->setTime(9, 0), true);
        $this->followUp($structura, $commercial, FollowUpReminderStage::DueDay, now()->setTime(9, 0), false);

        // -----------------------------------------------------------------
        // Boîte de réception
        // -----------------------------------------------------------------

        $this->email($mailbox, 'demo-mail-1@c2me.test', [
            'from_email' => 'nadia.lebreton@mecanique-derval.test',
            'from_name' => 'Nadia Lebreton',
            'subject' => 'Demande de devis — découpe laser',
            'body_text' => "Bonjour,\n\nPouvez-vous nous chiffrer 240 pièces en acier 3 mm ? Les plans sont joints au formulaire du site.\n\nCordialement,\nNadia Lebreton",
            'crm_account_id' => $derval->id,
            'crm_contact_id' => $nadia->id,
            'crm_opportunity_id' => $dervalOpportunity->id,
            'is_read' => false,
            'received_at' => now()->subHours(3),
        ]);

        $this->email($mailbox, 'demo-mail-2@c2me.test', [
            'from_email' => 'didier.moreau@fermes-toitures.test',
            'from_name' => 'Didier Moreau',
            'subject' => 'Plans du hangar agricole',
            'body_text' => "Bonjour Camille,\n\nVoici les cotes définitives pour le hangar de 1 200 m². Je reste disponible pour une visite sur place.\n\nDidier",
            'crm_account_id' => $fermes->id,
            'crm_contact_id' => $didier->id,
            'crm_opportunity_id' => $fermesOpportunity->id,
            'is_read' => false,
            'received_at' => now()->subDay(),
        ]);

        $this->email($mailbox, 'demo-mail-3@c2me.test', [
            'from_email' => 'pierre.chauvet@agro-bocage.test',
            'from_name' => 'Pierre Chauvet',
            'subject' => 'Retour sur le devis fromagerie',
            'body_text' => "Bonjour,\n\nLe devis est validé par la direction. Nous pouvons planifier une réunion de lancement la semaine prochaine.\n\nPierre Chauvet",
            'crm_account_id' => $agro->id,
            'crm_contact_id' => $pierre->id,
            'crm_opportunity_id' => $agroOpportunity->id,
            'is_read' => true,
            'received_at' => now()->subDays(2),
        ]);

        $this->email($mailbox, 'demo-mail-4@c2me.test', [
            'from_email' => 'contact@c2me.fr',
            'from_name' => 'C2ME',
            'subject' => 'Votre devis — découpe laser',
            'body_text' => "Bonjour Madame Lebreton,\n\nVeuillez trouver ci-joint notre devis pour la découpe des pièces acier.\n\nCordialement,\nCamille",
            'crm_account_id' => $derval->id,
            'crm_contact_id' => $nadia->id,
            'crm_opportunity_id' => $dervalOpportunity->id,
            'is_read' => true,
            'is_sent' => true,
            'folder' => 'Sent',
            'sent_at' => now()->subDay(),
            'received_at' => now()->subDay(),
        ]);

        $this->email($mailbox, 'demo-mail-5@c2me.test', [
            'from_email' => 'yann.dubois@atelier-metal-nantais.test',
            'from_name' => 'Yann Dubois',
            'subject' => 'Question sur la maintenance',
            'body_text' => "Bonjour,\n\nL’atelier est à l’arrêt mais je souhaite faire vérifier une passerelle. Est-ce que vous intervenez encore sur Rezé ?\n\nYann",
            'crm_account_id' => $metal->id,
            'crm_contact_id' => $yann->id,
            'is_read' => false,
            'received_at' => now()->subHours(8),
        ]);

        // -----------------------------------------------------------------
        // Demandes du site
        // -----------------------------------------------------------------

        $this->contactRequest([
            'name' => 'Nadia Lebreton',
            'company' => 'Mécanique Derval',
            'email' => 'nadia.lebreton@mecanique-derval.test',
            'phone' => '02 41 78 90 12',
            'request_type' => 'Découpe laser',
            'message' => 'Nous cherchons un partenaire pour des séries de 200 à 500 pièces en acier.',
            'crm_account_id' => $derval->id,
            'crm_contact_id' => $nadia->id,
            'read_at' => null,
            'created_at' => now()->subHours(5),
        ]);

        $this->contactRequest([
            'name' => 'Lucas Perrin',
            'company' => 'BET Structura Ingénierie',
            'email' => 'lucas.perrin@bet-structura.test',
            'phone' => '02 99 45 67 89',
            'request_type' => 'Charpente métallique',
            'message' => 'Nous avons besoin d’un chiffrage pour un hangar logistique de 1 500 m².',
            'crm_account_id' => $structura->id,
            'crm_contact_id' => $lucas->id,
            'read_at' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);

        $this->contactRequest([
            'name' => 'Isabelle Noël',
            'company' => null,
            'email' => 'isabelle.noel@example.test',
            'phone' => '06 11 22 33 44',
            'request_type' => 'Autre demande',
            'message' => 'Je souhaiterais visiter vos ateliers avec un groupe d’étudiants.',
            'crm_account_id' => null,
            'crm_contact_id' => null,
            'read_at' => null,
            'created_at' => now()->subDays(40),
        ]);

        // -----------------------------------------------------------------
        // Demandes de devis
        // -----------------------------------------------------------------

        $this->quoteRequest([
            'kind' => QuoteKind::Laser,
            'status' => QuoteStatus::New,
            'name' => 'Farid Benali',
            'company' => 'Serrures Benali',
            'email' => 'farid.benali@example.test',
            'phone' => '06 55 44 33 22',
            'city' => 'Clisson',
            'message' => '150 pièces en inox 304, épaisseur 2 mm, finition brossée.',
            'payload' => [
                'Matière' => 'Inox 304',
                'Épaisseur' => '2 mm',
                'Quantité' => '100 à 500 pièces',
                'Type de pièces' => 'Pièces techniques',
                'Dimensions' => '180 × 90 mm',
                'Délai' => 'Sous 3 semaines',
                'Livraison' => 'Retrait sur site',
            ],
            'created_at' => now()->subHours(6),
        ]);

        $structuraRequest = $this->quoteRequest([
            'kind' => QuoteKind::Laser,
            'status' => QuoteStatus::UnderStudy,
            'name' => 'Lucas Perrin',
            'company' => 'BET Structura Ingénierie',
            'email' => 'lucas.perrin@bet-structura.test',
            'phone' => '02 99 45 67 89',
            'city' => 'Rennes',
            'message' => 'Découpe de platines et goussets pour le hangar logistique. Plans joints.',
            'payload' => [
                'Matière' => 'Acier S235',
                'Épaisseur' => '8 mm',
                'Quantité' => '50 à 100 pièces',
                'Type de pièces' => 'Platines et goussets',
                'Dimensions' => '400 × 300 mm',
                'Délai' => 'Sous 4 semaines',
                'Livraison' => 'Livraison — Rennes',
            ],
            'crm_account_id' => $structura->id,
            'crm_contact_id' => $lucas->id,
            'crm_opportunity_id' => $structuraOpportunity->id,
            'created_at' => now()->subDays(3),
        ]);

        $this->quoteRequestFile($structuraRequest, 'plans-platines.pdf');
        $this->quoteRequestFile($structuraRequest, 'plan-hangar.pdf');

        $belliardRequest = $this->quoteRequest([
            'kind' => QuoteKind::Building,
            'status' => QuoteStatus::QuoteSent,
            'name' => 'Julie Fontaine',
            'company' => 'Constructions Belliard',
            'email' => 'julie.fontaine@constructions-belliard.test',
            'phone' => '02 41 90 12 34',
            'city' => 'Angers',
            'message' => 'Ossature métallique pour une plateforme logistique de 2 000 m², avec bardage.',
            'payload' => [
                'Type de bâtiment' => 'Bâtiment industriel',
                'Usage' => 'Stockage et logistique',
                'Commune' => 'Angers',
                'Dimensions' => 'L 80 m · l 25 m · H 9 m',
                'Enveloppe' => 'Bardage double peau',
                'Prestations' => 'Étude · Fabrication · Pose',
                'Calendrier' => '6 à 9 mois',
                'Budget' => '250 000 à 500 000 €',
                'Vous êtes' => 'Entreprise générale',
            ],
            'crm_account_id' => $belliard->id,
            'crm_contact_id' => $marc->id,
            'crm_opportunity_id' => $belliardOpportunity->id,
            'quoted_at' => now()->subDays(2),
            'created_at' => now()->subDays(12),
        ]);

        $this->quoteRequest([
            'kind' => QuoteKind::Building,
            'status' => QuoteStatus::New,
            'name' => 'Hélène Charrier',
            'company' => 'EARL des Prés Verts',
            'email' => 'helene.charrier@example.test',
            'phone' => '06 78 90 12 34',
            'city' => 'Saint-Fulgent',
            'message' => 'Hangar agricole de 1 000 m² pour stockage de fourrage et matériel.',
            'payload' => [
                'Type de bâtiment' => 'Bâtiment agricole',
                'Usage' => 'Stockage',
                'Commune' => 'Saint-Fulgent',
                'Dimensions' => 'L 50 m · l 20 m · H 7 m',
                'Enveloppe' => 'Bac acier',
                'Prestations' => 'Fabrication · Pose',
                'Calendrier' => '3 à 6 mois',
                'Budget' => '100 000 à 250 000 €',
                'Vous êtes' => 'Exploitant agricole',
            ],
            'created_at' => now()->subHours(20),
        ]);

        // -----------------------------------------------------------------
        // Calendrier
        // -----------------------------------------------------------------

        $this->calendarEvent($admin, 'Point commercial hebdomadaire', [
            'starts_at' => now()->setTime(9, 0),
            'ends_at' => now()->setTime(9, 30),
            'all_day' => false,
            'reminder_minutes' => 15,
            'recurrence' => 'weekly',
            'recurrence_until' => now()->addWeeks(8)->toDateString(),
            'share_with_all' => false,
        ]);

        $this->calendarEvent($admin, 'Rendez-vous — Atelier Aria', [
            'title' => 'Rendez-vous — Atelier Aria',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'all_day' => false,
            'reminder_minutes' => 30,
            'location' => '12 rue des Olivettes, 44000 Nantes',
            'description' => 'Revue des plans de l’extension et validation du calendrier.',
            'crm_account_id' => $aria->id,
            'crm_contact_id' => $claire->id,
            'share_with_all' => false,
        ], [$commercial]);

        $this->calendarEvent($commercial, 'Visite de chantier — Charpente Maine', [
            'starts_at' => now()->addDays(2)->setTime(14, 0),
            'ends_at' => now()->addDays(2)->setTime(15, 30),
            'all_day' => false,
            'reminder_minutes' => 60,
            'location' => '18 rue des Artisans, 72000 Le Mans',
            'description' => 'Relevé des cotes et photos de la couverture existante.',
            'crm_account_id' => $charpente->id,
            'crm_contact_id' => $julien->id,
            'share_with_all' => false,
        ], [$admin, $technician]);

        $this->calendarEvent($admin, 'Salon Eurobois', [
            'starts_at' => now()->addDays(5)->startOfDay(),
            'ends_at' => now()->addDays(6)->endOfDay(),
            'all_day' => true,
            'location' => 'Eurexpo, Lyon',
            'description' => 'Stand C2ME — hall 4, allée B.',
            'share_with_all' => true,
        ]);

        $this->calendarEvent($commercial, 'Congés', [
            'starts_at' => now()->addDays(20)->startOfDay(),
            'ends_at' => now()->addDays(24)->endOfDay(),
            'all_day' => true,
            'share_with_all' => true,
        ]);

        $this->calendarEvent($technician, 'Réunion bureau d’études', [
            'starts_at' => now()->addDay()->setTime(11, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
            'all_day' => false,
            'reminder_minutes' => 15,
            'location' => 'Visioconférence',
            'share_with_all' => true,
        ]);

        $this->command?->info(sprintf(
            'Démo complète : %d comptes, %d contacts, %d activités, %d devis, %d demandes, %d événements.',
            CrmAccount::query()->count(),
            CrmContact::query()->count(),
            CrmActivity::query()->count(),
            Quote::query()->count(),
            ContactRequest::query()->count() + QuoteRequest::query()->count(),
            CalendarEvent::query()->count(),
        ));
    }

    private function adminUser(): User
    {
        $emails = (array) config('site.admin.allowed_emails');
        $email = (string) ($emails[0] ?? 'admin@c2me.fr');
        $password = (string) config('site.admin.password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrateur C2ME',
                'password' => $password !== '' ? $password : 'password',
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        $user->forceFill([
            'name' => 'Administrateur C2ME',
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function demoUser(string $email, string $name, UserRole $role, ?string $jobTitle = null): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'is_active' => true,
                'job_title' => $jobTitle,
            ],
        );

        // Le mot de passe n'est volontairement pas réinitialisé : le seeder
        // ne doit pas écraser un mot de passe changé manuellement.
        $user->forceFill([
            'name' => $name,
            'role' => $role,
            'is_active' => true,
            'job_title' => $jobTitle,
        ])->save();

        return $user;
    }

    private function mailbox(User $owner): EmailMailbox
    {
        return EmailMailbox::query()->firstOrCreate(
            ['email' => 'contact@c2me.fr'],
            [
                'user_id' => $owner->id,
                'name' => 'Boîte commerciale',
                'is_shared' => true,
                'imap_host' => 'imap.orange.fr',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => 'contact@c2me.fr',
                'imap_password' => 'secret',
                'smtp_host' => 'smtp.orange.fr',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'smtp_username' => 'contact@c2me.fr',
                'smtp_password' => 'secret',
                'is_active' => true,
            ],
        );
    }

    private function seedTags(): void
    {
        $tags = [
            ['Chaud', CrmTagColor::Danger],
            ['Partenaire', CrmTagColor::Info],
            ['Grand compte', CrmTagColor::Success],
            ['À relancer', CrmTagColor::Warning],
            ['Chantier', CrmTagColor::Primary],
        ];

        foreach ($tags as [$name, $color]) {
            CrmTag::query()->updateOrCreate(
                ['name' => $name],
                ['color' => $color->value],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $tagNames
     */
    private function account(User $owner, array $attributes, array $tagNames): CrmAccount
    {
        $account = CrmAccount::query()->updateOrCreate(
            ['name' => (string) $attributes['name']],
            [...$attributes, 'owner_id' => $owner->id],
        );

        $account->tags()->sync(
            CrmTag::query()->whereIn('name', $tagNames)->pluck('id')->all(),
        );

        return $account;
    }

    private function contact(CrmAccount $account, string $firstName, string $lastName, string $email, string $jobTitle, bool $primary = false): CrmContact
    {
        return CrmContact::query()->updateOrCreate(
            ['email' => $email],
            [
                'crm_account_id' => $account->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'job_title' => $jobTitle,
                'phone' => '02 40 00 00 00',
                'is_primary' => $primary,
                'decision_maker' => $primary,
                'do_not_contact' => false,
            ],
        );
    }

    private function opportunity(CrmAccount $account, CrmContact $contact, User $owner, string $title, OpportunityStage $stage, float $amount): CrmOpportunity
    {
        return CrmOpportunity::query()->updateOrCreate(
            ['crm_account_id' => $account->id, 'title' => $title],
            [
                'crm_contact_id' => $contact->id,
                'owner_id' => $owner->id,
                'stage' => $stage,
                'amount_estimated' => $amount,
                'probability' => $stage->defaultProbability(),
                'expected_close_date' => now()->addDays(45)->toDateString(),
                'lost_reason' => $stage === OpportunityStage::Lost ? 'Budget reporté à l’année prochaine.' : null,
                'won_at' => $stage === OpportunityStage::Won ? now()->subDays(5) : null,
                'closed_at' => $stage->isClosed() ? now()->subDays(5) : null,
            ],
        );
    }

    /**
     * Crée un historique cohérent : trois interactions passées, une relance
     * planifiée et, selon les comptes, une tâche en retard.
     *
     * @return CrmActivity L’activité de visite, pour y rattacher des photos.
     */
    private function seedTimeline(CrmAccount $account, CrmContact $contact, CrmOpportunity $opportunity, User $owner, string $topic, int $overdueDays = 2): CrmActivity
    {
        $this->activity($account, $contact, $opportunity, $owner, 'Appel — '.$topic, [
            'type' => CrmActivityType::Call,
            'body' => 'Premier échange pour cadrer le besoin et identifier les interlocuteurs.',
            'outcome' => 'Besoin confirmé, envoi des plans demandé.',
            'occurred_at' => now()->subDays(12)->setTime(10, 30),
            'due_at' => null,
            'remind_at' => null,
            'completed_at' => null,
        ]);

        $this->activity($account, $contact, $opportunity, $owner, 'E-mail — '.$topic, [
            'type' => CrmActivityType::Email,
            'body' => 'Transmission de la documentation technique et des références de chantier.',
            'outcome' => 'Documents envoyés, relance prévue à une semaine.',
            'occurred_at' => now()->subDays(7)->setTime(15, 15),
            'due_at' => null,
            'remind_at' => null,
            'completed_at' => null,
        ]);

        $visit = $this->activity($account, $contact, $opportunity, $owner, 'Visite — '.$topic, [
            'type' => CrmActivityType::Visit,
            'body' => 'Visite sur site : relevé des cotes, accès et contraintes de chantier.',
            'outcome' => 'Relevés réalisés, deux photos ajoutées au dossier.',
            'occurred_at' => now()->subDays(3)->setTime(9, 0),
            'due_at' => null,
            'remind_at' => null,
            'completed_at' => null,
        ]);

        $this->activity($account, $contact, $opportunity, $owner, 'Relance — '.$topic, [
            'type' => CrmActivityType::FollowUp,
            'body' => 'Faire le point sur le devis et la date de décision.',
            'outcome' => null,
            'occurred_at' => null,
            'due_at' => now()->addDays(2)->setTime(9, 0),
            'remind_at' => now()->addDays(2)->setTime(8, 30),
            'completed_at' => null,
        ]);

        if ($overdueDays > 0) {
            $this->activity($account, $contact, $opportunity, $owner, 'Tâche — préparer le chiffrage '.$topic, [
                'type' => CrmActivityType::Task,
                'body' => 'Finaliser les métrés et vérifier les prix matières avant édition du devis.',
                'outcome' => null,
                'occurred_at' => null,
                'due_at' => now()->subDays($overdueDays)->setTime(17, 0),
                'remind_at' => now()->subDays($overdueDays)->setTime(16, 0),
                'completed_at' => null,
            ]);
        }

        return $visit;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activity(CrmAccount $account, CrmContact $contact, ?CrmOpportunity $opportunity, User $owner, string $subject, array $attributes): CrmActivity
    {
        return CrmActivity::query()->updateOrCreate(
            ['crm_account_id' => $account->id, 'subject' => $subject],
            [
                'crm_contact_id' => $contact->id,
                'crm_opportunity_id' => $opportunity?->id,
                'user_id' => $owner->id,
                ...$attributes,
            ],
        );
    }

    private function attachPhoto(CrmActivity $activity, string $fileName, string $clientUuid): void
    {
        $binary = base64_decode(self::DEMO_JPEG, true);
        $contents = $binary === false ? '' : $binary;
        $path = (string) config('crm.attachments.directory', 'crm-activity-attachments')
            .'/demo/'.$activity->id.'/'.$fileName;

        $disk = CrmActivityAttachment::disk();

        if (! Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->put($path, $contents);
        }

        CrmActivityAttachment::query()->updateOrCreate(
            ['crm_activity_id' => $activity->id, 'original_name' => $fileName],
            [
                'client_uuid' => $clientUuid,
                'path' => $path,
                'mime_type' => 'image/jpeg',
                'size' => strlen($contents),
            ],
        );
    }

    private function followUp(CrmAccount $account, User $user, FollowUpReminderStage $stage, CarbonInterface $dueAt, bool $sent): void
    {
        CrmFollowUpReminder::query()->updateOrCreate(
            [
                'crm_account_id' => $account->id,
                'user_id' => $user->id,
                'stage' => $stage->value,
            ],
            [
                'due_at' => $dueAt,
                'database_sent_at' => $sent ? $dueAt : null,
                'mail_sent_at' => $sent ? $dueAt : null,
                'attempts' => $sent ? 1 : 0,
                'last_error' => null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<User>  $sharedUsers
     */
    private function calendarEvent(User $owner, string $title, array $attributes, array $sharedUsers = []): CalendarEvent
    {
        $event = CalendarEvent::query()->updateOrCreate(
            ['user_id' => $owner->id, 'title' => $title],
            [...$attributes, 'title' => $title],
        );

        if (($attributes['share_with_all'] ?? false) !== true) {
            $event->sharedUsers()->sync(
                array_map(static fn (User $user): int => $user->id, $sharedUsers),
            );
        } else {
            $event->sharedUsers()->sync([]);
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function email(EmailMailbox $mailbox, string $messageId, array $attributes): EmailMessage
    {
        return EmailMessage::query()->updateOrCreate(
            ['email_mailbox_id' => $mailbox->id, 'message_id' => $messageId],
            [
                'folder' => 'INBOX',
                'uid' => $messageId,
                'to_recipients' => [['email' => 'contact@c2me.fr', 'name' => 'C2ME']],
                'cc_recipients' => [],
                'is_read' => true,
                'is_sent' => false,
                'received_at' => now(),
                ...$attributes,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function contactRequest(array $attributes): ContactRequest
    {
        $readAt = $attributes['read_at'] ?? null;
        $createdAt = $attributes['created_at'] ?? null;

        unset($attributes['read_at'], $attributes['created_at']);

        $request = ContactRequest::query()->firstOrCreate(
            [
                'email' => (string) $attributes['email'],
                'message' => (string) $attributes['message'],
            ],
            $attributes,
        );

        if ($request->wasRecentlyCreated) {
            $request->forceFill([
                'read_at' => $readAt,
                'created_at' => $createdAt,
            ])->saveQuietly();
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function quoteRequest(array $attributes): QuoteRequest
    {
        $createdAt = $attributes['created_at'] ?? null;
        $quotedAt = $attributes['quoted_at'] ?? null;

        unset($attributes['created_at'], $attributes['quoted_at']);

        $request = QuoteRequest::query()->firstOrCreate(
            [
                'email' => (string) $attributes['email'],
                'message' => (string) $attributes['message'],
            ],
            $attributes,
        );

        if ($request->wasRecentlyCreated) {
            $request->forceFill([
                'quoted_at' => $quotedAt,
                'created_at' => $createdAt,
            ])->saveQuietly();
        }

        return $request;
    }

    private function quoteRequestFile(QuoteRequest $request, string $fileName): void
    {
        $path = (string) config('site.quote.storage_directory', 'quote-requests')
            .'/demo/'.$request->id.'/'.$fileName;

        $disk = QuoteRequestFile::disk();

        if (! Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->put($path, self::DEMO_PDF);
        }

        QuoteRequestFile::query()->firstOrCreate(
            ['quote_request_id' => $request->id, 'original_name' => $fileName],
            [
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen(self::DEMO_PDF),
            ],
        );
    }

    /**
     * @param  list<array{designation: string, quantity: float, unit: string, unit_price: float}>  $lines
     */
    private function quote(CrmAccount $account, CrmContact $contact, ?CrmOpportunity $opportunity, User $owner, string $subject, QuoteDocumentStatus $status, array $lines): Quote
    {
        $quote = Quote::query()->firstOrCreate(
            ['crm_account_id' => $account->id, 'subject' => $subject],
            [
                'crm_contact_id' => $contact->id,
                'crm_opportunity_id' => $opportunity?->id,
                'owner_id' => $owner->id,
                'status' => $status,
                'notes' => 'Devis de démonstration généré par DemoDataSeeder.',
                'issued_at' => now()->subDays(10)->toDateString(),
                'valid_until' => now()->addDays(20)->toDateString(),
                'amount_ht' => 0,
                'vat_rate' => 20,
            ],
        );

        foreach ($lines as $index => $line) {
            $quote->lines()->updateOrCreate(
                ['position' => $index + 1],
                [
                    'designation' => $line['designation'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => 0,
                ],
            );
        }

        return $quote;
    }
}
