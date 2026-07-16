# dolibarr-helloasso-uv
Create members and subscriptions from HelloAsso webhook

Add this module in `/htdocs/custom/` folder as explain in [Dolibarr documentation](https://wiki.dolibarr.org/index.php/D%C3%A9veloppement_module#Cr.C3.A9er_un_module).

Webhook may be configured in [HelloAsso API integration](https://admin.helloasso.com/<your-org>/integrations)

This module may be activated from [Dolibarr modules configuration](https://<your-dolibarr>/admin/modules.php?mainmenu=home) :

<img alt="dolibarr-helloassso-admin" src="https://github.com/user-attachments/assets/8bcbebd8-e806-4192-a3d4-90e316741dcc" />

Defautl API user may be defined in [module admin](https://<your-dolibarr>/custom/helloasso/admin/helloasso.php).

# Fonctionnement général

A la réception d'un paiement HelloAsso (helloasso_process_payload - ne gère que les évènement de type 'Order' provenant de HelloAsso), le module crée ou récupère un tiers associé à l'email utilisé dans HelloAsso. Le module crée ensuite une nouvelle adhésion (sur base d'un produit/service) et la facture associée.

Qu'on crée ou met à jour un tiers avec sa nouvelle adhésion, le module ajoute un tag avec la période de l'adhésion dans le champ "Cotisation" avec les 2 années d'adhésion, ex: 2627 pour la période du 1er juillet 2026 au 30 juin 2027 (calculé dans la fonction setPeriod de la classe HelloassoMember: ```$breakpoint = $year .'-07-01';```).

On associe également pour les nouveaux adhérents un statut et un massif :
 - le massif est directement récupéré depuis le champ complété dans HelloAsso
 - le statut est récupéré depuis le libellé du produit/service Dolibarr associé au nom du "Montant" dans HelloAsso, ex: "Producteur.trice de PPAM MENTION SIMPLES-TRANCHE 1".

A ce jour, il manque l'envoie de la facture par email (@TODO)
