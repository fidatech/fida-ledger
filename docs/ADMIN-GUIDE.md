# Guide for system administrators (/admin)

The admin panel runs the platform: companies, payments, versions. It has its own sign-in
(`/admin/login`) and its own accounts, separate from company users.

**Administrators cannot open a company's books.** There is no screen for it, by design. To help
a customer, ask them to add you as a user in Settings > Users & Roles.

## Roles

Created with `php artisan app:create-admin` or under Administrators. Managers and super
administrators can decide payments and write announcements; other administrators read.

## Daily work

**Payment approvals.** A company becomes active only here. For each payment: open the receipt
(cash payments have none), compare the amount with what was really received, then Approve or
Reject with a reason. Approval starts or extends the subscription by 12 months. Uploading a
receipt never activates anything by itself.

**Companies.** Search, open a company to see its version, subscription dates, users and
payments. You can change its version (this switches its modules; nothing is charged
automatically, so collect the difference yourself), change its end date, or suspend it. A
suspended company sees only its billing page.

**Users.** Everyone who can sign in to a company. Block stops a person signing in everywhere.

## Set-up

**Subscription Plans.** Setup fee, yearly fee and user limit of Starter and Business. A change
applies to payments made from then on.

**Module Entitlements.** Which Business modules each version includes. Saving applies to every
company of that version.

**Announcements.** A message shown to companies on their dashboard, with dates and a tone.

## Looking back

**Revenue Reports** (approved payments by year, setup against renewals), **Audit Logs** (what
administrators did), **System Health** (bcmath, storage, mail, debug mode, scheduler).

## Languages

The globe in the header switches English, Dari and Pashto for your browser.
