<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle       = 'Privacy Policy — Apex Cybernet Tournament';
$pageDescription = 'Privacy Policy for Apex Cybernet Tournament. Learn how we collect, use, and protect your personal information under RA 10173 (Data Privacy Act of the Philippines).';
require_once __DIR__ . '/includes/header.php';
?>
<div class="reg-container" style="max-width:720px;">
    <a href="<?= base_url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to home</a>
    <div class="reg-card">
        <h1 style="font-size:1.5rem; font-weight:800; margin-bottom:0.25rem;"><i class="bi bi-shield-lock"></i> Privacy Policy</h1>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:2rem;">Last updated: April 13, 2026 · Apex Cybernet</p>

        <?php
        $sections = [
            ['title' => '1. Introduction and Scope', 'body' => '
                Apex Cybernet ("Apex Cybernet," "we," "us," "our") operates the Apex Cybernet Tournament Platform at apexcybernet.com
                (the "Platform"). We are a one-person corporation registered and operating under the laws of the Republic
                of the Philippines, with principal place of business in Cebu City, Philippines.
                <br><br>
                This Privacy Policy explains how we collect, use, process, store, share, and protect your personal
                information when you access or use our Platform. This Policy applies to all visitors, registered users,
                tournament participants, and any other person who interacts with the Platform in any capacity
                (collectively, "Users" or "you").
                <br><br>
                This Privacy Policy is issued in compliance with Republic Act No. 10173, also known as the Data Privacy
                Act of 2012 ("DPA"), its Implementing Rules and Regulations ("IRR"), and all relevant issuances, circulars,
                and advisory opinions of the National Privacy Commission ("NPC").
                <br><br>
                By accessing or using the Platform, you acknowledge that you have read, understood, and agree to the
                collection and processing of your personal information as described in this Privacy Policy. If you do not
                agree with any part of this Policy, you must immediately cease using the Platform.
                <br><br>
                This Privacy Policy should be read in conjunction with our
                <a href="' . base_url('terms.php') . '" style="color:var(--accent-light);">Terms of Service</a>.
                Capitalized terms not defined herein have the meanings given to them in the Terms of Service.
            '],
            ['title' => '2. Data Protection Officer', 'body' => '
                In compliance with the DPA and NPC requirements, Apex Cybernet has designated a Data Protection Officer ("DPO")
                who is responsible for the oversight of the company\'s data protection strategy and implementation to ensure
                compliance with the DPA and its IRR.
                <br><br>
                For any questions, concerns, requests, or complaints regarding this Privacy Policy, your personal data,
                or our data processing practices, you may contact our DPO through the following:
                <br><br>
                <strong style="color:var(--text-main);">Data Protection Officer</strong><br>
                Apex Cybernet<br>
                Cebu City, Philippines<br>
                <br><br>
                We will acknowledge receipt of your inquiry within five (5) business days and respond substantively within
                fifteen (15) business days, in accordance with NPC guidelines.
            '],
            ['title' => '3. Information We Collect', 'body' => '
                We collect only the information that is reasonably necessary to operate the Platform, provide our services,
                and comply with our legal obligations. The types of information we collect include:
                <br><br>
                <strong style="color:var(--text-main);">3.1 Information You Provide Directly</strong>
                <br><br>
                <strong style="color:var(--text-main);">a) Account Registration Data</strong><br>
                When you create an Account, we collect:<br>
                • Display name (username) — used as your public identifier on the Platform<br>
                • Email address — used for account recovery and essential communications<br>
                • Password — stored exclusively as a one-way bcrypt hash; we never store or have access to your plain-text password<br>
                • Contact number (optional) — used to coordinate tournament logistics and prize distribution
                <br><br>
                <strong style="color:var(--text-main);">b) Tournament Registration Data</strong><br>
                When you register for a tournament, we collect:<br>
                • Team name<br>
                • Team member names (or aliases) and in-game ranks/tiers<br>
                • Game-specific information (preferred roles, rank tiers)<br>
                • Payment method and proof of payment (if applicable)
                <br><br>
                <strong style="color:var(--text-main);">c) Profile Data</strong><br>
                You may optionally provide:<br>
                • Profile picture (uploaded image)<br>
                • Bio/description<br>
                • Referral code usage
                <br><br>
                <strong style="color:var(--text-main);">d) User-Generated Content</strong><br>
                • Dispute submissions (player name, subject, description of issue)
                <br><br>
                <strong style="color:var(--text-main);">e) Communication Data</strong><br>
                • Any messages, feedback, or inquiries you submit through the our communication channels
                <br><br>
                <strong style="color:var(--text-main);">3.2 Information Collected Automatically</strong>
                <br><br>
                <strong style="color:var(--text-main);">a) Server Logs</strong><br>
                When you access the Platform, our servers automatically record standard technical information, including:<br>
                • Internet Protocol (IP) address<br>
                • Browser type and version<br>
                • Operating system<br>
                • Referring URL<br>
                • Pages visited and features used<br>
                • Date and time of access<br>
                • HTTP response codes<br>
                This data is retained for security and operational purposes only and is purged after thirty (30) days.
                <br><br>
                <strong style="color:var(--text-main);">b) Session Data</strong><br>
                We use a single session cookie (PHPSESSID) to maintain your authenticated session. This is strictly necessary
                for the Platform to function. See Section 10 for details on our cookie practices.
                <br><br>
                <strong style="color:var(--text-main);">3.3 Information We Do NOT Collect</strong><br>
                We want to be transparent about what we do not collect:<br>
                • We do not use advertising or tracking cookies<br>
                • We do not use analytics services (such as Google Analytics or Facebook Pixel)<br>
                • We do not collect biometric data<br>
                • We do not collect location data beyond IP-derived geography<br>
                • We do not collect financial account numbers, credit card numbers, or bank details (e-wallet payments are
                processed directly through the e-wallet platform, not through our servers)<br>
                • We do not collect data from social media accounts or third-party platforms
            '],
            ['title' => '4. How We Use Your Information', 'body' => '
                We process your personal information for the following specific, legitimate purposes:
                <br><br>
                <strong style="color:var(--text-main);">4.1 Account Management</strong><br>
                • To create, maintain, and administer your Account<br>
                • To authenticate your identity and maintain session security<br>
                • To communicate essential Account-related information (security alerts, changes to Terms)
                <br><br>
                <strong style="color:var(--text-main);">4.2 Tournament Operations</strong><br>
                • To register your team and generate tournament brackets<br>
                • To display team names, member names, and ranks on public brackets and standings<br>
                • To manage match scheduling, results, and advancement<br>
                • To administer and distribute prizes
                <br><br>
                <strong style="color:var(--text-main);">4.3 Platform Safety and Integrity</strong><br>
                • To detect, investigate, and prevent fraud, abuse, multi-accounting, and Terms violations<br>
                • To investigate and resolve disputes between Users<br>
                • To maintain platform security and prevent unauthorized access
                <br><br>
                <strong style="color:var(--text-main);">4.4 Communication</strong><br>
                • To send on-Platform notifications about matches and tournament updates<br>
                • To respond to your inquiries and support requests<br>
                • To notify you of material changes to our Terms of Service or Privacy Policy
                <br><br>
                <strong style="color:var(--text-main);">4.5 Legal Compliance</strong><br>
                • To comply with applicable Philippine laws, regulations, and legal processes<br>
                • To respond to lawful requests from government authorities<br>
                • To establish, exercise, or defend legal claims
                <br><br>
                <strong style="color:var(--text-main);">4.6 Service Improvement</strong><br>
                • To understand how Users interact with the Platform for the purpose of improving features and user experience<br>
                • To identify and fix bugs, errors, and technical issues<br>
                • This is done using aggregated, non-identifiable data only
            '],
            ['title' => '5. Legal Basis for Processing', 'body' => '
                Under Section 12 of the Data Privacy Act (RA 10173), we process your personal information on the following
                lawful bases:
                <br><br>
                <strong style="color:var(--text-main);">5.1 Contractual Necessity</strong><br>
                Processing that is necessary to perform the services you registered for, including Account management
                and tournament participation. Without this processing, we cannot
                provide the services you requested.
                <br><br>
                <strong style="color:var(--text-main);">5.2 Legitimate Interests</strong><br>
                Processing that is necessary for our legitimate interests, including platform security, fraud prevention,
                tournament administration, dispute resolution, and service improvement, provided that such interests are
                not overridden by your fundamental rights and freedoms. We conduct a balancing test to ensure our legitimate
                interests do not unduly impact your rights.
                <br><br>
                <strong style="color:var(--text-main);">5.3 Legal Obligation</strong><br>
                Processing that is necessary to comply with Philippine law, including but not limited to responding to valid
                court orders, government requests, tax obligations, and regulatory requirements.
                <br><br>
                <strong style="color:var(--text-main);">5.4 Consent</strong><br>
                Where you have explicitly and freely given your consent for a specific purpose, such as receiving optional
                promotional communications or participating in surveys. Where processing is based on consent, you have the
                right to withdraw your consent at any time. Withdrawal of consent does not affect the lawfulness of
                processing based on consent before its withdrawal.
                <br><br>
                <strong style="color:var(--text-main);">5.5 Vital Interests</strong><br>
                In exceptional circumstances, processing may be necessary to protect your vital interests or those of
                another natural person, such as in the case of a medical emergency at a tournament venue.
            '],
            ['title' => '6. Sharing and Disclosure of Information', 'body' => '
                <strong style="color:var(--text-main);">6.1 We Do Not Sell Your Data</strong><br>
                Apex Cybernet does not sell, rent, trade, or otherwise commercially transfer your personal information to third
                parties for their own marketing or business purposes. We have never sold personal data and have no plans
                to do so.
                <br><br>
                <strong style="color:var(--text-main);">6.2 Public Information</strong><br>
                The following information is publicly visible on the Platform by design:<br>
                • Your display name (username)<br>
                • Your team name and game rank (displayed on tournament brackets and standings)<br>
                • Tournament results, match scores, and bracket placements<br>
                • Titles and achievements displayed on your public profile
                <br><br>
                <strong style="color:var(--text-main);">6.3 Authorized Disclosures</strong><br>
                We may share your personal information with the following categories of recipients, only to the extent
                necessary for the stated purposes:
                <br><br>
                (a) <strong style="color:var(--text-main);">Tournament Participants</strong> — Your team name, member
                names/aliases, and game ranks are shared with other tournament participants as part of bracket and match
                information. This is essential for the operation of the tournament.<br><br>
                (b) <strong style="color:var(--text-main);">Payment Processors</strong> — When you make a payment,
                your e-wallet number or payment reference is shared with the e-wallet provider to
                process the transaction. We share only the minimum information necessary to complete the payment.<br><br>
                (c) <strong style="color:var(--text-main);">Hosting and Infrastructure Providers</strong> — Your data is
                stored on servers provided by our hosting provider. These providers have access to data only as necessary
                to perform their hosting services and are bound by their own privacy and security obligations.<br><br>
                (d) <strong style="color:var(--text-main);">Law Enforcement and Government Authorities</strong> — We may
                disclose your information if required by a valid Philippine court order, subpoena, or government request
                issued in accordance with applicable law, including the DPA, the Cybercrime Prevention Act of 2012 (RA 10175),
                or other applicable legislation. We will notify you of such requests where legally permitted.<br><br>
                (e) <strong style="color:var(--text-main);">Legal Proceedings</strong> — We may disclose your information
                to the extent necessary to establish, exercise, or defend legal claims, or when disclosure is necessary
                for the administration of justice.<br><br>
                (f) <strong style="color:var(--text-main);">Business Transfers</strong> — In the event of a merger,
                acquisition, reorganization, sale of assets, or bankruptcy, your personal information may be transferred
                as part of that transaction. We will notify you via on-Platform notification and/or email of any change
                in ownership or uses of your personal information, as well as any choices you may have regarding your
                personal information.
                <br><br>
                <strong style="color:var(--text-main);">6.4 Anonymized and Aggregated Data</strong><br>
                We may share anonymized, aggregated, or de-identified data that cannot reasonably be used to identify you
                for any purpose, including research, analytics, and business development. Such data is not considered
                personal information under the DPA.
            '],
            ['title' => '7. Data Retention', 'body' => '
                We retain your personal data in accordance with the following retention schedule, balancing our operational
                needs with data minimization principles under the DPA:
                <br><br>
                <strong style="color:var(--text-main);">7.1 Account Data</strong><br>
                Retained for as long as your Account is active and for a period of one (1) year following Account deletion
                or termination, to allow for Account recovery, dispute resolution, and legal compliance.
                <br><br>
                <strong style="color:var(--text-main);">7.2 Tournament Records</strong><br>
                Brackets, match results, team names, standings, and tournament history may be retained indefinitely as part
                of the Platform\'s public historical record. This data serves the legitimate interest of maintaining a
                comprehensive tournament archive for the esports community.
                <br><br>
                <strong style="color:var(--text-main);">7.3 Payment Records</strong><br>
                Payment records, including tournament entry-fee payments, are retained for a
                minimum of three (3) years from the date of the transaction for financial record-keeping, audit, and
                regulatory compliance purposes.
                <br><br>
                <strong style="color:var(--text-main);">7.4 Server Logs</strong><br>
                Standard server access logs (IP addresses, browser data, page visits) are automatically purged after thirty
                (30) days. Logs related to security incidents may be retained for up to one (1) year.
                <br><br>
                <strong style="color:var(--text-main);">7.5 Communication Records</strong><br>
                Support inquiries and communications are retained for one (1) year to track resolution history.
                <br><br>
                <strong style="color:var(--text-main);">7.6 Dispute and Violation Records</strong><br>
                Records related to disputes, Terms violations, and enforcement actions may be retained for up to five (5)
                years or as required by applicable law.
                <br><br>
                <strong style="color:var(--text-main);">7.7 Deletion</strong><br>
                When personal data is no longer needed for the purposes for which it was collected, or upon valid request,
                we will securely delete or anonymize such data using industry-standard methods. Some data may be retained
                in anonymized form for statistical purposes.
            '],
            ['title' => '8. Data Security', 'body' => '
                We implement reasonable and appropriate technical, organizational, and physical security measures to protect
                your personal data against unauthorized access, alteration, disclosure, destruction, loss, and other forms of
                unlawful processing, in compliance with Section 20 of the DPA and NPC Circular 2016-01.
                <br><br>
                <strong style="color:var(--text-main);">8.1 Organizational Measures</strong><br>
                • Access to personal data is restricted to authorized personnel on a need-to-know basis<br>
                • Administrative access requires multi-factor authentication (password plus PIN)<br>
                • Regular review of access controls and security practices
                <br><br>
                <strong style="color:var(--text-main);">8.2 Incident Response</strong><br>
                In the event of a personal data breach that is likely to result in a risk to your rights and freedoms, we
                will: (a) notify the National Privacy Commission within seventy-two (72) hours of becoming aware of the
                breach, as required by NPC Circular 2016-03; (b) notify affected Users without undue delay where the breach
                is likely to result in a high risk to rights and freedoms; (c) take immediate steps to contain the breach
                and mitigate any harmful effects; (d) document the breach, its effects, and the remedial actions taken.
                <br><br>
                <strong style="color:var(--text-main);">8.3 No Absolute Security</strong><br>
                While we strive to use commercially acceptable means to protect your personal data, no method of transmission
                over the Internet or method of electronic storage is 100% secure. We cannot guarantee absolute security.
                If you suspect any unauthorized access to your Account or personal data, please contact us immediately.
            '],
            ['title' => '9. Your Rights Under the Data Privacy Act', 'body' => '
                Under the Data Privacy Act of 2012 (RA 10173), you have the following rights as a data subject. We are
                committed to facilitating the exercise of these rights in a timely manner:
                <br><br>
                <strong style="color:var(--text-main);">9.1 Right to Be Informed</strong><br>
                You have the right to be informed of the collection and processing of your personal data, including the
                purposes, scope, and method of processing, the identity of the personal information controller, and the
                period for which your data will be stored. This Privacy Policy serves as the primary notice of our data
                processing activities.
                <br><br>
                <strong style="color:var(--text-main);">9.2 Right to Access</strong><br>
                You have the right to request access to your personal data that we hold, including: (a) the contents of
                your personal data that were processed; (b) the sources from which it was obtained; (c) the names and
                addresses of recipients of the data; (d) the manner by which the data was processed; (e) the reasons for
                the disclosure to recipients, if any; (f) information on automated processing where the data will or is
                likely to be used as the sole basis for any decision that significantly affects the data subject; (g) the
                date when the data was last accessed and modified; (h) the designation, name or identity, and address of
                the personal information controller.
                <br><br>
                <strong style="color:var(--text-main);">9.3 Right to Rectification</strong><br>
                You have the right to dispute the inaccuracy or error in your personal data and have us correct it
                immediately and accordingly, unless the request is vexatious or otherwise unreasonable. You may update
                most of your Account information directly through the Platform. For corrections that cannot be made through
                the Platform, please contact our DPO.
                <br><br>
                <strong style="color:var(--text-main);">9.4 Right to Erasure or Blocking</strong><br>
                You have the right to request the suspension, withdrawal, blocking, removal, or destruction of your personal
                data from our filing systems under any of the following conditions: (a) the data is incomplete, outdated,
                false, or unlawfully obtained; (b) it is being used for a purpose not authorized by you; (c) it is no longer
                necessary for the purpose for which it was collected; (d) you withdraw your consent and there is no other
                legal ground for processing; (e) the data concerns private information that is prejudicial to you, unless
                justified by freedom of speech, of expression, or of the press, or otherwise authorized; (f) processing is
                unlawful; (g) we violated your rights as a data subject.
                <br><br>
                <strong style="color:var(--text-main);">9.5 Right to Object</strong><br>
                You have the right to object to the processing of your personal data, including processing based on
                legitimate interests or for direct marketing purposes. Upon your objection, we will no longer process the
                personal data, unless we demonstrate compelling legitimate grounds for the processing which override your
                interests, rights, and freedoms, or for the establishment, exercise, or defense of legal claims.
                <br><br>
                <strong style="color:var(--text-main);">9.6 Right to Data Portability</strong><br>
                You have the right to receive your personal data in a structured, commonly used, and machine-readable format
                (such as JSON or CSV), and to have it transmitted directly to another personal information controller, where
                technically feasible.
                <br><br>
                <strong style="color:var(--text-main);">9.7 Right to File a Complaint</strong><br>
                You have the right to lodge a complaint with the National Privacy Commission if you believe that your rights
                under the DPA have been violated. The NPC can be contacted at:
                <br><br>
                National Privacy Commission<br>
                3rd Floor, Core G, GSIS Headquarters, Financial Center, Pasay City 1308<br>
                Website: <strong>privacy.gov.ph</strong><br>
                Complaints Hotline: (02) 8234-2228
                <br><br>
                <strong style="color:var(--text-main);">9.8 Right to Damages</strong><br>
                You have the right to be indemnified for any damages sustained due to inaccurate, incomplete, outdated,
                false, unlawfully obtained, or unauthorized use of your personal data, considering any violation of your
                rights and freedoms as a data subject.
                <br><br>
                <strong style="color:var(--text-main);">9.9 How to Exercise Your Rights</strong><br>
                To exercise any of these rights, please contact our Data Protection Officer using the
                our contact channels.
                We may require you to verify your identity before processing your request. We will acknowledge receipt
                within five (5) business days and respond substantively within fifteen (15) business days. Complex requests
                may require up to thirty (30) business days, in which case we will inform you of the extended timeline.
                <br><br>
                <strong style="color:var(--text-main);">9.10 Limitations</strong><br>
                Certain rights may be limited where permitted by law, such as when processing is necessary for compliance
                with a legal obligation, for the establishment, exercise, or defense of legal claims, or for the protection
                of public interest.
            '],
            ['title' => '10. Cookies and Tracking Technologies', 'body' => '
                <strong style="color:var(--text-main);">10.1 Our Cookie Use</strong><br>
                The Platform uses only a single, strictly necessary session cookie:
                <br><br>
                <strong>Cookie Name:</strong> PHPSESSID<br>
                <strong>Type:</strong> Session cookie (strictly necessary)<br>
                <strong>Purpose:</strong> Maintains your authenticated login session<br>
                <strong>Duration:</strong> Expires when you close your browser or log out<br>
                <strong>Data Stored:</strong> A randomly generated session identifier only — no personal data is stored
                in the cookie itself
                <br><br>
                <strong style="color:var(--text-main);">10.2 No Third-Party Cookies</strong><br>
                We do not use any advertising cookies, analytics cookies, tracking pixels, web beacons, or any other
                third-party tracking technology. We do not participate in any advertising networks or retargeting programs.
                We do not use Google Analytics, Facebook Pixel, or any similar service.
                <br><br>
                <strong style="color:var(--text-main);">10.3 Do Not Track</strong><br>
                Because we do not use any tracking technologies beyond the strictly necessary session cookie, there is no
                tracking behavior to modify in response to "Do Not Track" browser signals. Your browsing on our Platform
                is not tracked across other websites.
                <br><br>
                <strong style="color:var(--text-main);">10.4 Local Storage</strong><br>
                The Platform may use browser local storage or session storage for temporary UI state (such as notification
                preferences or form data). This data remains on your device, is not transmitted to our servers, and is
                cleared when you clear your browser data.
            '],
            ['title' => '11. Children\'s Privacy', 'body' => '
                <strong style="color:var(--text-main);">11.1 Age Restrictions</strong><br>
                We do not knowingly collect personal data from children
                under the age of thirteen (13). Tournament bracket registration by minors aged 13-17 is permitted only with
                verifiable parental or legal guardian consent.
                <br><br>
                <strong style="color:var(--text-main);">11.2 Parental Rights</strong><br>
                If you are a parent or legal guardian and believe that your child under the age of 13 has provided personal
                information to us without your consent, please contact us immediately using the
                our contact channels.
                We will take prompt steps to verify the claim and delete such information from our records.
                <br><br>
                <strong style="color:var(--text-main);">11.3 Special Protection</strong><br>
                In compliance with RA 7610 (Special Protection of Children Against Abuse, Exploitation and Discrimination Act)
                and the DPA\'s provisions on sensitive personal information, we afford special protection to the personal data
                of minors and implement additional safeguards when processing such data.
            '],
            ['title' => '12. International Data Transfers', 'body' => '
                <strong style="color:var(--text-main);">12.1 Primary Data Location</strong><br>
                Your personal data is primarily stored and processed on servers located in jurisdictions where our hosting
                provider operates. While we endeavor to use servers within the Asia-Pacific region, data may be transferred
                to and processed in other countries as part of our hosting infrastructure.
                <br><br>
                <strong style="color:var(--text-main);">12.2 Transfer Safeguards</strong><br>
                Where personal data is transferred outside the Philippines, we ensure that adequate safeguards are in place
                in compliance with Section 21 of the DPA and NPC Circular 2016-02. Such safeguards may include contractual
                clauses with our service providers that require them to protect personal data to a standard comparable to
                that required by the DPA.
                <br><br>
                <strong style="color:var(--text-main);">12.3 User Consent</strong><br>
                By using the Platform, you consent to the transfer and processing of your personal data outside the Philippines
                where necessary for the provision of our services, subject to the safeguards described above.
            '],
            ['title' => '13. Automated Decision-Making', 'body' => '
                <strong style="color:var(--text-main);">13.1 Bracket Seeding</strong><br>
                The Platform uses automated processes to seed tournament brackets based on team member ranks. This automated
                processing is used solely for tournament administration purposes and does not constitute a decision that
                significantly affects your rights.
                <br><br>
                <strong style="color:var(--text-main);">13.2 No Profiling</strong><br>
                We do not engage in profiling as defined under the DPA. We do not use your personal data to make automated
                decisions that have legal or similarly significant effects on you.
            '],
            ['title' => '14. Third-Party Services', 'body' => '
                <strong style="color:var(--text-main);">14.1 Payment Processing</strong><br>
                Payments on the Platform are processed through supported e-wallet providers. When you make a
                payment, your transaction is subject to the e-wallet provider\'s own terms and
                privacy policy. We recommend you review their privacy practices before using their services.
                <br><br>
                <strong style="color:var(--text-main);">14.2 Hosting and Infrastructure</strong><br>
                The Platform is hosted on third-party server infrastructure. Our hosting providers are contractually bound
                to maintain appropriate security measures and to process data only as instructed by us.
                <br><br>
                <strong style="color:var(--text-main);">14.3 CDN and External Resources</strong><br>
                The Platform loads certain resources (fonts, CSS frameworks, icons) from third-party content delivery networks
                (CDNs) such as Google Fonts, Bootstrap CDN, and jsDelivr. These CDN providers may collect limited technical
                data (such as IP address) as part of serving these resources. We do not control the privacy practices of
                these CDN providers.
                <br><br>
                <strong style="color:var(--text-main);">14.4 Links to External Sites</strong><br>
                The Platform may contain links to external websites or services. We are not responsible for the privacy
                practices of external sites. We encourage you to review the privacy policies of any external sites you visit.
            '],
            ['title' => '15. Data Breach Notification', 'body' => '
                <strong style="color:var(--text-main);">15.1 NPC Notification</strong><br>
                In accordance with NPC Circular 2016-03 (Personal Data Breach Management), we will notify the National Privacy
                Commission within seventy-two (72) hours of becoming aware of a personal data breach where there is a
                reasonable belief that the breach involves sensitive personal information or is likely to result in a real
                risk of serious harm to any affected data subject.
                <br><br>
                <strong style="color:var(--text-main);">15.2 User Notification</strong><br>
                We will notify affected Users of a personal data breach without undue delay where the breach is likely to
                result in a high risk to their rights and freedoms. Notification will include: (a) the nature of the breach;
                (b) the personal data potentially compromised; (c) measures taken to address the breach; (d) recommendations
                for Users to protect themselves; (e) contact information for further inquiries.
                <br><br>
                <strong style="color:var(--text-main);">15.3 Documentation</strong><br>
                We maintain records of all personal data breaches, including the facts surrounding the breach, its effects,
                and the remedial actions taken, regardless of whether the breach requires notification.
            '],
            ['title' => '16. Changes to This Privacy Policy', 'body' => '
                <strong style="color:var(--text-main);">16.1 Updates</strong><br>
                We may update this Privacy Policy from time to time to reflect changes in our data practices, legal
                requirements, or operational needs. The "Last updated" date at the top of this page will be revised with
                each update.
                <br><br>
                <strong style="color:var(--text-main);">16.2 Notification of Material Changes</strong><br>
                For material changes that significantly affect how we collect, use, or share your personal data, we will
                provide prominent notice through on-Platform notifications at least thirty (30) days before the changes
                take effect. Where required by law, we will obtain your consent before implementing material changes.
                <br><br>
                <strong style="color:var(--text-main);">16.3 Continued Use</strong><br>
                Your continued use of the Platform after the effective date of any updated Privacy Policy constitutes your
                acceptance of the updated Policy. If you do not agree with the updated Policy, you should discontinue use
                of the Platform and request Account deletion.
                <br><br>
                <strong style="color:var(--text-main);">16.4 Prior Versions</strong><br>
                Previous versions of this Privacy Policy may be requested by contacting our DPO.
            '],
            ['title' => '17. Data Minimization and Purpose Limitation', 'body' => '
                <strong style="color:var(--text-main);">17.1 Data Minimization</strong><br>
                In compliance with the principle of data minimization under Section 18 of the DPA, we collect only the minimum
                amount of personal data that is reasonably necessary to fulfill the purposes described in this Privacy Policy.
                We do not collect data "just in case" or for speculative future use. Each data point we collect has a specific,
                documented purpose.
                <br><br>
                <strong style="color:var(--text-main);">17.2 Purpose Limitation</strong><br>
                Personal data collected for a specific purpose will not be processed in a manner incompatible with that purpose
                without obtaining your additional consent, unless such further processing is: (a) necessary for compliance with
                a legal obligation; (b) necessary for the establishment, exercise, or defense of legal claims; (c) necessary to
                protect your vital interests or those of another natural person; (d) necessary for purposes of public interest.
                <br><br>
                <strong style="color:var(--text-main);">17.3 Storage Limitation</strong><br>
                We do not retain personal data longer than is necessary for the purposes for which it was collected. Our
                retention periods are outlined in Section 7 of this Privacy Policy. When personal data is no longer needed,
                it is securely deleted or anonymized in accordance with industry-standard practices.
                <br><br>
                <strong style="color:var(--text-main);">17.4 Accuracy</strong><br>
                We take reasonable steps to ensure that personal data we hold is accurate, complete, and up-to-date. You are
                responsible for providing accurate information during registration and for updating your information when it
                changes. You can update your Account information at any time through the Platform.
            '],
            ['title' => '18. Accountability and Governance', 'body' => '
                <strong style="color:var(--text-main);">18.1 Accountability Principle</strong><br>
                Apex Cybernet, as the personal information controller, is accountable for personal data under its
                control or custody, including data that has been transferred to a third party for processing, whether
                domestically or internationally. We implement appropriate organizational, physical, and technical measures
                to ensure compliance with the DPA and to demonstrate such compliance upon request.
                <br><br>
                <strong style="color:var(--text-main);">18.2 Privacy Impact Assessments</strong><br>
                For new features, services, or data processing activities that are likely to result in a high risk to the
                rights and freedoms of data subjects, Apex Cybernet conducts privacy impact assessments to identify and mitigate
                risks before implementation. This includes assessments for new features,
                payment integrations, and any changes that significantly affect how personal data is processed.
                <br><br>
                <strong style="color:var(--text-main);">18.3 Staff Training</strong><br>
                All individuals with access to personal data are made aware of their obligations under the DPA and these
                privacy policies. Access to personal data is granted on a need-to-know basis and is subject to appropriate
                confidentiality obligations.
                <br><br>
                <strong style="color:var(--text-main);">18.4 Records of Processing</strong><br>
                We maintain records of our data processing activities as required by the DPA and NPC issuances. These records
                include the categories of data processed, purposes of processing, categories of data subjects and recipients,
                retention periods, and a general description of technical and organizational security measures.
                <br><br>
                <strong style="color:var(--text-main);">18.5 Subcontractor Oversight</strong><br>
                Where we engage third-party service providers to process personal data on our behalf, we ensure that
                appropriate contractual safeguards are in place requiring the service provider to: (a) process personal data
                only as instructed by Apex Cybernet; (b) implement appropriate security measures; (c) notify Apex Cybernet of any data
                breach without undue delay; (d) delete or return personal data upon termination of the service agreement;
                (e) submit to audits and inspections to verify compliance.
            '],
            ['title' => '19. Special Categories of Data', 'body' => '
                <strong style="color:var(--text-main);">19.1 Sensitive Personal Information</strong><br>
                We do not intentionally collect sensitive personal information as defined under Section 3(l) of the DPA,
                which includes but is not limited to: race, ethnic origin, marital status, age (except as necessary for
                eligibility verification), color, religious, philosophical, or political affiliations, health, education,
                genetic or sexual life, legal proceedings, government-issued identifiers (SSS, GSIS, TIN, PhilHealth, etc.),
                or any information established by an executive order or an act of Congress to be kept classified.
                <br><br>
                <strong style="color:var(--text-main);">19.2 Inadvertent Collection</strong><br>
                If you inadvertently provide sensitive personal information to us (for example, in a dispute submission,
                support inquiry, or profile bio), we will make reasonable efforts to delete such information upon discovery,
                unless retention is required by law or necessary for the establishment, exercise, or defense of legal claims.
                <br><br>
                <strong style="color:var(--text-main);">19.3 Privileged Information</strong><br>
                We do not collect or process privileged information as defined under Section 3(n) of the DPA, including but
                not limited to information protected by attorney-client privilege, doctor-patient confidentiality, or any
                other form of legally recognized privilege.
            '],
            ['title' => '20. Data Subject Request Procedures', 'body' => '
                <strong style="color:var(--text-main);">20.1 Submission of Requests</strong><br>
                Data subject requests (access, rectification, erasure, objection, portability, or any other right under
                Section 9) may be submitted through our official communication channels. All requests must include sufficient
                information to verify your identity and to identify the specific data or processing activity to which the
                request relates.
                <br><br>
                <strong style="color:var(--text-main);">20.2 Identity Verification</strong><br>
                To protect your privacy and security, we will verify your identity before processing any data subject request.
                Verification may require you to provide information matching the data we hold on file, to confirm your identity
                through your registered email address, or to provide additional documentation in cases of sensitive requests.
                We will not process requests from unverified individuals.
                <br><br>
                <strong style="color:var(--text-main);">20.3 Response Timelines</strong><br>
                We will acknowledge receipt of your request within five (5) business days. We will provide a substantive
                response within fifteen (15) business days of receiving a verified request. For complex or voluminous requests,
                the response period may be extended to thirty (30) business days, in which case we will notify you of the
                extension and the reasons for it within the initial fifteen-day period.
                <br><br>
                <strong style="color:var(--text-main);">20.4 Fees</strong><br>
                We do not charge fees for processing data subject requests. However, where requests are manifestly unfounded,
                excessive, or repetitive, we reserve the right to charge a reasonable fee based on administrative costs or
                to refuse to act on the request, in accordance with the DPA.
                <br><br>
                <strong style="color:var(--text-main);">20.5 Refusal of Requests</strong><br>
                We may refuse a data subject request where: (a) the request is manifestly unfounded, vexatious, or excessive;
                (b) complying with the request would adversely affect the rights and freedoms of other data subjects;
                (c) the data is required for compliance with a legal obligation; (d) the data is required for the establishment,
                exercise, or defense of legal claims; (e) an exemption under the DPA applies. Where a request is refused,
                we will inform you of the reasons for the refusal and of your right to file a complaint with the NPC.
                <br><br>
                <strong style="color:var(--text-main);">20.6 Record of Requests</strong><br>
                We maintain a log of all data subject requests received, including the nature of the request, the date
                received, the date of response, the outcome, and any reasons for refusal. This log is maintained in
                compliance with the accountability requirements of the DPA and may be provided to the NPC upon request.
            '],
            ['title' => '21. Anonymization and Pseudonymization', 'body' => '
                <strong style="color:var(--text-main);">21.1 Anonymization</strong><br>
                Where possible and appropriate, we anonymize personal data so that it can no longer be attributed to a
                specific data subject. Anonymized data is not considered personal information under the DPA and may be
                used by Apex Cybernet for any purpose, including but not limited to research, analytics, statistical analysis,
                service improvement, and business development, without restriction.
                <br><br>
                <strong style="color:var(--text-main);">21.2 Pseudonymization</strong><br>
                In certain processing contexts, we may pseudonymize personal data by replacing identifying information
                with artificial identifiers. Pseudonymized data is still considered personal information under the DPA
                because it can be re-identified with additional information. We apply appropriate safeguards to ensure
                that the additional information necessary for re-identification is kept separately and is protected by
                technical and organizational measures.
                <br><br>
                <strong style="color:var(--text-main);">21.3 Tournament Data</strong><br>
                Tournament results, bracket data, match scores, and standings are published using display names (usernames)
                and team names. These are considered public information voluntarily provided by Users for the purpose of
                tournament participation and are not subject to anonymization. Users acknowledge that their display names,
                team names, and tournament performance data will be publicly visible and may be retained indefinitely as
                part of the Platform\'s historical record.
            '],
            ['title' => '22. Consent Management', 'body' => '
                <strong style="color:var(--text-main);">22.1 Collection of Consent</strong><br>
                Where processing is based on consent, we collect your consent through clear and affirmative action, such as
                checking a consent checkbox during registration. Consent is freely given, specific, informed, and unambiguous.
                Pre-checked boxes, silence, or inactivity do not constitute valid consent.
                <br><br>
                <strong style="color:var(--text-main);">22.2 Withdrawal of Consent</strong><br>
                You have the right to withdraw your consent at any time. Withdrawal of consent does not affect the lawfulness
                of processing based on consent before its withdrawal. To withdraw consent, contact us through our official
                communication channels. We will process your withdrawal request within fifteen (15) business days. Please
                note that withdrawal of consent may affect your ability to use certain features of the Platform.
                <br><br>
                <strong style="color:var(--text-main);">22.3 Consent for Minors</strong><br>
                For Users under the age of 18, consent must be provided or authorized by the User\'s parent or legal guardian.
                Apex Cybernet reserves the right to request proof of parental or guardian consent before allowing minors to use
                the Platform.
                <br><br>
                <strong style="color:var(--text-main);">22.4 Records of Consent</strong><br>
                We maintain records of consents obtained, including the date, time, method, and scope of each consent.
                These records serve as evidence of compliance with the consent requirements of the DPA.
            '],
            ['title' => '23. Dispute Resolution for Privacy Matters', 'body' => '
                <strong style="color:var(--text-main);">23.1 Internal Resolution</strong><br>
                If you have a concern or complaint about how we handle your personal data, we encourage you to contact our
                Data Protection Officer first. We will investigate your concern and respond within fifteen (15) business days.
                We are committed to resolving privacy complaints through internal processes wherever possible.
                <br><br>
                <strong style="color:var(--text-main);">23.2 Escalation</strong><br>
                If you are not satisfied with our response or if your complaint is not resolved to your satisfaction within
                thirty (30) business days, you have the right to escalate your complaint to the National Privacy Commission.
                The NPC provides a free complaint resolution mechanism for data subjects whose rights under the DPA have
                been violated.
                <br><br>
                <strong style="color:var(--text-main);">23.3 Cooperation with Authorities</strong><br>
                Apex Cybernet will cooperate fully with the National Privacy Commission, the Department of Justice, and any other
                relevant government authority in connection with any investigation, audit, inquiry, or complaint relating to
                our data processing activities. We will provide all requested information and access in a timely manner.
                <br><br>
                <strong style="color:var(--text-main);">23.4 No Retaliation</strong><br>
                Apex Cybernet will not retaliate against any User who, in good faith, files a complaint, exercises their data
                subject rights, or cooperates with a regulatory investigation. Any form of retaliation, including Account
                termination, service degradation, or harassment, is strictly prohibited.
            '],
            ['title' => '24. Governing Law for Privacy', 'body' => '
                This Privacy Policy and all matters arising from or relating to the collection, processing, and protection
                of your personal data shall be governed by and construed in accordance with the laws of the Republic of the
                Philippines, including but not limited to Republic Act No. 10173 (Data Privacy Act of 2012), its Implementing
                Rules and Regulations, and all applicable issuances of the National Privacy Commission. Any disputes relating
                to this Privacy Policy that cannot be resolved through the procedures described in Section 23 shall be
                subject to the exclusive jurisdiction of the appropriate courts of Cebu City, Philippines.
            '],
            ['title' => '25. Contact Information', 'body' => '
                For any questions, concerns, complaints, or requests related to this Privacy Policy or our data processing
                practices, please contact us:
                <br><br>
                <strong style="color:var(--text-main);">Apex Cybernet</strong><br>
                Cebu City, Philippines<br>
                <br><br>
                We take all privacy concerns seriously and will respond to your inquiry within fifteen (15) business days.
            '],
        ];
        foreach ($sections as $s):
        ?>
        <div style="margin-bottom:1.75rem;">
            <h2 style="font-size:1rem; font-weight:800; color:var(--text-main); margin-bottom:0.5rem;"><?= $s['title'] ?></h2>
            <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.8; margin:0;"><?= $s['body'] ?></p>
        </div>
        <?php endforeach; ?>

        <div style="border-top:1px solid var(--border); padding-top:1rem; margin-top:1rem; font-size:0.75rem; color:var(--text-muted); text-align:center;">
            <a href="<?= base_url('terms.php') ?>" style="color:var(--accent-light); text-decoration:none;">Terms of Service</a>
            &nbsp;·&nbsp;
            <a href="<?= base_url('rules.php') ?>" style="color:var(--accent-light); text-decoration:none;">Tournament Rules</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
