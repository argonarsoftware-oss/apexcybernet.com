<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle       = 'Terms of Service — Apex Cybernet Tournament';
$pageDescription = 'Terms of Service for Apex Cybernet Tournament, including tournament registration, account rules, and platform terms.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="reg-container" style="max-width:720px;">
    <a href="<?= base_url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to home</a>
    <div class="reg-card">
        <h1 style="font-size:1.5rem; font-weight:800; margin-bottom:0.25rem;"><i class="bi bi-file-text"></i> Terms of Service</h1>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:2rem;">Last updated: April 13, 2026 · Apex Cybernet</p>

        <?php
        $sections = [
            ['title' => '1. Acceptance of Terms', 'body' => '
                By accessing, browsing, or using the Apex Cybernet Tournament platform ("Platform"), available at apexcybernet.com and any
                associated subdomains, mobile applications, APIs, or services operated by Apex Cybernet, you acknowledge
                that you have read, understood, and agree to be bound by these Terms of Service ("Terms"), our
                <a href="' . base_url('privacy.php') . '" style="color:var(--accent-light);">Privacy Policy</a>, and our
                <a href="' . base_url('rules.php') . '" style="color:var(--accent-light);">Tournament Rules</a>,
                all of which are incorporated herein by reference.
                <br><br>
                If you do not agree to any provision of these Terms, you must immediately cease all use of the Platform.
                Your continued use of the Platform following the posting of any changes to these Terms constitutes your
                binding acceptance of such changes, whether or not you have reviewed them.
                <br><br>
                These Terms apply to all visitors, registered users, tournament participants, spectators,
                and any other person who accesses or uses the Platform in any capacity (collectively, "Users" or "you").
                <br><br>
                Apex Cybernet reserves the right, at its sole discretion, to modify, amend, supplement, or replace
                these Terms at any time. Material changes will be communicated through on-Platform notifications, and the
                "Last updated" date above will be revised accordingly. It is your responsibility to review these Terms
                periodically. Your continued use after any modification constitutes acceptance of the modified Terms.
            '],
            ['title' => '2. Definitions', 'body' => '
                For the purposes of these Terms, the following definitions apply:
                <br><br>
                <strong style="color:var(--text-main);">"Platform"</strong> means the Apex Cybernet Tournament website at apexcybernet.com,
                including all pages, features, tools, APIs, subdomains, and any mobile or desktop applications provided by
                Apex Cybernet, whether now existing or developed in the future.
                <br><br>
                <strong style="color:var(--text-main);">"Apex Cybernet," "we," "us," "our"</strong> refers to Apex Cybernet,
                a one-person corporation registered and operating under the laws of the Republic of the Philippines, with
                principal place of business in Cebu City, Philippines.
                <br><br>
                <strong style="color:var(--text-main);">"User," "you," "your"</strong> refers to any individual who accesses,
                browses, registers on, or otherwise uses the Platform, whether as a visitor, registered account holder,
                tournament participant, or in any other capacity.
                <br><br>
                <strong style="color:var(--text-main);">"Account"</strong> means a registered user profile on the Platform
                created through the registration process, identified by a unique display name and email address.
                <br><br>
                <strong style="color:var(--text-main);">"Content"</strong> means any text, images, graphics, logos, audio, video,
                data, software, code, user-generated submissions, tournament results, and any other materials available on or
                through the Platform.
                <br><br>
                <strong style="color:var(--text-main);">"Force Majeure"</strong> means any event beyond the reasonable control
                of Apex Cybernet, including but not limited to natural disasters, pandemics, government actions, civil unrest, war,
                terrorism, internet disruptions, power outages, hardware failures, cyberattacks, or other events beyond reasonable control.
            '],
            ['title' => '3. Eligibility and Account Registration', 'body' => '
                <strong style="color:var(--text-main);">3.1 Age Requirements</strong><br>
                You must be at least eighteen (18) years of age to create an Account. By creating an Account, you
                represent and warrant that you are at least 18 years old. Participants under the age of 18 may register for
                tournament brackets only with verifiable parental or legal guardian consent.
                <br><br>
                <strong style="color:var(--text-main);">3.2 Account Creation</strong><br>
                To access certain features of the Platform, you must register for an Account by providing a valid email address,
                a unique display name (username), and a password. You agree to provide accurate, current, and complete information
                during registration and to update such information to keep it accurate, current, and complete.
                <br><br>
                <strong style="color:var(--text-main);">3.3 Account Security</strong><br>
                You are solely responsible for maintaining the confidentiality of your Account credentials, including your
                password, and for all activities that occur under your Account. You agree to immediately notify Apex Cybernet of
                any unauthorized use of your Account or any other breach of security. Apex Cybernet shall not be liable for any
                loss or damage arising from your failure to secure your Account credentials.
                <br><br>
                <strong style="color:var(--text-main);">3.4 One Account Per Person</strong><br>
                Each individual is permitted to maintain only one (1) Account on the Platform. Creating, operating, or
                controlling multiple accounts ("multi-accounting") is strictly prohibited and constitutes a material violation
                of these Terms. Apex Cybernet reserves the right to suspend, terminate, or merge duplicate accounts without notice
                or compensation.
                <br><br>
                <strong style="color:var(--text-main);">3.5 Account Termination</strong><br>
                Apex Cybernet may suspend, restrict, or permanently terminate your Account at any time, with or without cause,
                with or without notice, including but not limited to situations where Apex Cybernet reasonably believes that you
                have violated these Terms, engaged in fraudulent activity, manipulated platform features, or posed a risk
                to the Platform, its Users, or the integrity of any tournament. Upon termination, your right to use the
                Platform will immediately cease.
                <br><br>
            '],
            ['title' => '4. Tournament Registration and Participation', 'body' => '
                <strong style="color:var(--text-main);">4.1 Registration</strong><br>
                Tournament registration is subject to availability and the specific rules of each tournament event.
                By registering for a tournament, you agree to abide by the
                <a href="' . base_url('rules.php') . '" style="color:var(--accent-light);">Tournament Rules</a>
                applicable to that event, as well as these Terms.
                <br><br>
                <strong style="color:var(--text-main);">4.2 Registration Fees</strong><br>
                Registration fees, once paid, are non-refundable except at the sole and absolute discretion of Apex Cybernet.
                Apex Cybernet is under no obligation to issue refunds for registration fees under any circumstances, including but not
                limited to team withdrawal, disqualification, tournament postponement, format changes, or cancellation due to
                Force Majeure events. Where a tournament is cancelled entirely due to reasons within Apex Cybernet\'s control,
                Apex Cybernet may, but is not obligated to, issue partial or full refunds.
                <br><br>
                <strong style="color:var(--text-main);">4.3 Disqualification</strong><br>
                Apex Cybernet reserves the right to disqualify any team or individual participant for any of the following reasons,
                without limitation: (a) violation of Tournament Rules or these Terms; (b) unsportsmanlike conduct, including
                verbal abuse, threats, harassment, or intimidation; (c) use of cheats, hacks, exploits, unauthorized software,
                or any form of match manipulation; (d) match-fixing, collusion, or any conduct that jeopardizes the competitive
                integrity of the tournament; (e) failure to appear for a scheduled match within the designated time window;
                (f) providing false or misleading information during registration; (g) any other conduct that Apex Cybernet, in its
                sole discretion, deems detrimental to the tournament or its participants. Disqualification decisions are final
                and not subject to appeal.
                <br><br>
                <strong style="color:var(--text-main);">4.4 Schedule and Format Changes</strong><br>
                Bracket seedings, match schedules, tournament formats (including but not limited to bracket size, number of rounds,
                elimination format, and game selection), and prize structures are subject to change at Apex Cybernet\'s sole discretion
                at any time before, during, or after the tournament. Apex Cybernet will make reasonable efforts to communicate such
                changes to participants but is not liable for any inconvenience, loss, or damage resulting from such changes.
                <br><br>
                <strong style="color:var(--text-main);">4.5 Prizes</strong><br>
                Prize amounts, formats, and distribution methods are determined solely by Apex Cybernet and are subject to change.
                Prize claims are subject to verification of identity and compliance with these Terms. Any taxes, duties, or
                other charges applicable to prizes are the sole responsibility of the prize recipient. Apex Cybernet may withhold
                prizes pending the outcome of any investigation into potential rule violations. Prizes are non-transferable
                unless otherwise stated.
                <br><br>
                <strong style="color:var(--text-main);">4.6 Sportsmanship</strong><br>
                All participants are expected to maintain a standard of good sportsmanship throughout the tournament. This
                includes treating opponents, organizers, spectators, and venue staff with respect, accepting match results
                gracefully, and refraining from any behavior that could be construed as bullying, discrimination, or harassment.
                Violations of sportsmanship standards may result in warnings, point deductions, match forfeiture, disqualification,
                account suspension, or permanent bans from future events.
            '],
            ['title' => '5. User Conduct and Prohibited Activities', 'body' => '
                <strong style="color:var(--text-main);">5.1 General Conduct</strong><br>
                You agree to use the Platform only for lawful purposes and in a manner consistent with these Terms and all
                applicable local, national, and international laws, rules, and regulations, including but not limited to the
                laws of the Republic of the Philippines.
                <br><br>
                <strong style="color:var(--text-main);">5.2 Prohibited Activities</strong><br>
                You agree not to, and shall not permit any third party to:
                <br><br>
                (a) Use the Platform for any unlawful, fraudulent, or malicious purpose;<br>
                (b) Attempt to gain unauthorized access to any portion of the Platform, other Users\' Accounts, or any systems
                or networks connected to the Platform;<br>
                (c) Use any automated means (including bots, scrapers, crawlers, or spiders) to access, monitor, or copy any
                portion of the Platform without Apex Cybernet\'s prior written consent;<br>
                (d) Manipulate, or attempt to manipulate, match outcomes or any other Platform feature through collusion,
                exploits, bugs, or any other means;<br>
                (e) Create, operate, or control multiple Accounts;<br>
                (f) Share, sell, transfer, or otherwise make available your Account credentials to any third party;<br>
                (g) Impersonate any person or entity, or falsely state or misrepresent your affiliation with any person or entity;<br>
                (h) Harass, abuse, threaten, stalk, or intimidate any other User, participant, organizer, or staff member;<br>
                (i) Post, transmit, or distribute any content that is unlawful, defamatory, obscene, hateful, discriminatory,
                or that infringes on any third party\'s intellectual property or privacy rights;<br>
                (j) Interfere with or disrupt the Platform or servers or networks connected to the Platform, including by
                transmitting any viruses, malware, worms, or other malicious code;<br>
                (k) Reverse engineer, decompile, disassemble, or otherwise attempt to derive the source code of the Platform;<br>
                (l) Circumvent, disable, or otherwise interfere with security-related features of the Platform;<br>
                (m) Use the Platform to engage in money laundering, terrorist financing, or any other financial crime;<br>
                (n) Exploit any bug, error, or design flaw in the Platform for personal advantage, and fail to promptly report
                the same to Apex Cybernet;<br>
                (o) Engage in any activity that could create liability for Apex Cybernet or cause Apex Cybernet to lose (in whole or in part)
                the services of its internet service providers, hosting providers, or other suppliers.
                <br><br>
                <strong style="color:var(--text-main);">5.3 Enforcement</strong><br>
                Violations of this Section may result in immediate account suspension or termination, disqualification from
                current and future tournaments, reporting to appropriate law
                enforcement authorities, and pursuit of any available legal remedies. Apex Cybernet\'s failure to enforce any
                provision of these Terms shall not constitute a waiver of that provision or any other provision.
            '],
            ['title' => '6. Intellectual Property', 'body' => '
                <strong style="color:var(--text-main);">6.1 Apex Cybernet Content</strong><br>
                All Content on the Platform, including but not limited to text, graphics, logos, icons, images, audio clips,
                video clips, data compilations, software, source code, page layout, design elements, and the overall "look and
                feel" of the Platform, is the exclusive property of Apex Cybernet or its licensors and is protected by
                Philippine copyright law (Republic Act No. 8293 — Intellectual Property Code of the Philippines) and
                international copyright treaties.
                <br><br>
                <strong style="color:var(--text-main);">6.2 Limited License</strong><br>
                Subject to your compliance with these Terms, Apex Cybernet grants you a limited, non-exclusive, non-transferable,
                non-sublicensable, revocable license to access and use the Platform for your personal, non-commercial use.
                This license does not include: (a) any right to reproduce, distribute, publicly display, or publicly perform
                any Content; (b) any right to modify, create derivative works of, reverse engineer, or disassemble any Content
                or software; (c) any right to use any data mining, robots, or similar data gathering and extraction methods;
                (d) any right to download (other than page caching) any portion of the Platform, except as expressly permitted
                by Apex Cybernet.
                <br><br>
                <strong style="color:var(--text-main);">6.3 Trademarks</strong><br>
                "Apex Cybernet," "Apex Cybernet Tournament," the Apex Cybernet logo, and all related names, logos, product and service
                names, designs, and slogans are trademarks or service marks of Apex Cybernet. You must not use such marks
                without the prior written permission of Apex Cybernet. All other names, logos, product and service names, designs,
                and slogans on the Platform are the trademarks of their respective owners.
                <br><br>
                <strong style="color:var(--text-main);">6.4 User Content</strong><br>
                By submitting, posting, or displaying any content on the Platform (including but not limited to team names,
                display names, profile information, and dispute submissions), you grant Apex Cybernet a
                worldwide, non-exclusive, royalty-free, perpetual, irrevocable, sublicensable, and transferable license to use,
                reproduce, modify, adapt, publish, translate, create derivative works from, distribute, and display such content
                in connection with the Platform and Apex Cybernet\'s business. You represent and warrant that you own or have the
                necessary rights to grant this license.
                <br><br>
                <strong style="color:var(--text-main);">6.5 DMCA / Takedown Requests</strong><br>
                If you believe that Content on the Platform infringes your copyright or other intellectual property rights,
                please contact us through the <a href="' . base_url('contact.php') . '" style="color:var(--accent-light);">contact form</a>
                with a detailed description of the alleged infringement.
            '],
            ['title' => '7. Disclaimers and Limitation of Liability', 'body' => '
                <strong style="color:var(--text-main);">7.1 "AS IS" and "AS AVAILABLE"</strong><br>
                THE PLATFORM AND ALL CONTENT, FEATURES, AND SERVICES PROVIDED THROUGH THE PLATFORM ARE PROVIDED ON AN
                "AS IS" AND "AS AVAILABLE" BASIS WITHOUT ANY WARRANTIES OF ANY KIND, WHETHER EXPRESS, IMPLIED, STATUTORY,
                OR OTHERWISE. TO THE FULLEST EXTENT PERMITTED BY APPLICABLE LAW, APEX CYBERNET DISCLAIMS ALL WARRANTIES, INCLUDING
                BUT NOT LIMITED TO IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE,
                NON-INFRINGEMENT, AND ANY WARRANTIES ARISING FROM COURSE OF DEALING OR USAGE OF TRADE.
                <br><br>
                <strong style="color:var(--text-main);">7.2 No Guarantee of Availability</strong><br>
                Apex Cybernet does not warrant that the Platform will be uninterrupted, timely, secure, error-free, or free of
                viruses or other harmful components. Apex Cybernet does not warrant that the results obtained from the use of the
                Platform will be accurate or reliable. Temporary or permanent interruptions, outages, or maintenance periods
                may occur without notice.
                <br><br>
                <strong style="color:var(--text-main);">7.3 Limitation of Liability</strong><br>
                TO THE MAXIMUM EXTENT PERMITTED BY THE LAWS OF THE REPUBLIC OF THE PHILIPPINES, APEX CYBERNET, ITS
                OWNER, OFFICERS, DIRECTORS, EMPLOYEES, AGENTS, AFFILIATES, SUCCESSORS, AND ASSIGNS SHALL NOT BE LIABLE TO
                YOU OR ANY THIRD PARTY FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, PUNITIVE, OR EXEMPLARY DAMAGES,
                INCLUDING BUT NOT LIMITED TO DAMAGES FOR LOSS OF PROFITS, GOODWILL, USE, DATA, OR OTHER
                INTANGIBLE LOSSES, REGARDLESS OF WHETHER SUCH DAMAGES ARE BASED ON CONTRACT, TORT, NEGLIGENCE, STRICT
                LIABILITY, OR ANY OTHER LEGAL THEORY, AND REGARDLESS OF WHETHER APEX CYBERNET HAS BEEN ADVISED OF THE POSSIBILITY
                OF SUCH DAMAGES.
                <br><br>
                <strong style="color:var(--text-main);">7.4 Specific Exclusions</strong><br>
                Without limiting the generality of the foregoing, Apex Cybernet shall not be liable for: (a) damages resulting from
                unauthorized access to your Account due to your failure to maintain Account security;
                (b) losses resulting from tournament cancellation, postponement, or modification; (c) damages resulting from
                Force Majeure events; (d) any action or inaction of third parties, including other Users, payment processors, or
                hosting providers; (e) losses arising from reliance on any Content on the Platform.
                <br><br>
                <strong style="color:var(--text-main);">7.5 Essential Basis</strong><br>
                The limitations of liability in this Section reflect a fair and reasonable allocation of risk between Apex Cybernet
                and you, and form an essential basis of the bargain between Apex Cybernet and you. Apex Cybernet would not be able to
                provide the Platform to you on an economically reasonable basis without these limitations.
            '],
            ['title' => '8. Indemnification', 'body' => '
                You agree to indemnify, defend, and hold harmless Apex Cybernet, its owner, officers, directors,
                employees, agents, affiliates, successors, and assigns from and against any and all claims, liabilities,
                damages, judgments, awards, losses, costs, expenses, and fees (including reasonable attorneys\' fees) arising
                out of or relating to: (a) your use of the Platform; (b) your violation of these Terms; (c) your violation
                of any third party\'s rights, including intellectual property rights, privacy rights, or other proprietary
                rights; (d) any content you submit, post, or transmit through the Platform; (e) your participation in any
                tournament; (f) any dispute between you and another User; (g) any claim that your content caused damage to a
                third party. This indemnification
                obligation shall survive the termination of these Terms and your use of the Platform.
            '],
            ['title' => '9. Dispute Resolution', 'body' => '
                <strong style="color:var(--text-main);">9.1 Informal Resolution</strong><br>
                Before filing any formal claim, you agree to first attempt to resolve the dispute informally by contacting
                Apex Cybernet through the <a href="' . base_url('contact.php') . '" style="color:var(--accent-light);">contact form</a>.
                Apex Cybernet will attempt to resolve the dispute informally within thirty (30) days. If the dispute is not resolved
                within this period, either party may proceed to formal dispute resolution.
                <br><br>
                <strong style="color:var(--text-main);">9.2 Mediation</strong><br>
                If informal resolution fails, the parties agree to first attempt mediation before a mutually agreed-upon
                mediator in Cebu City, Philippines, before resorting to litigation. The costs of mediation shall be shared
                equally between the parties.
                <br><br>
                <strong style="color:var(--text-main);">9.3 Jurisdiction</strong><br>
                Any dispute, claim, or controversy arising out of or relating to these Terms or the use of the Platform that
                cannot be resolved through informal resolution or mediation shall be subject to the exclusive jurisdiction of
                the appropriate courts of Cebu City, Philippines, and you consent to the personal jurisdiction of such courts.
                <br><br>
                <strong style="color:var(--text-main);">9.4 Class Action Waiver</strong><br>
                TO THE EXTENT PERMITTED BY APPLICABLE LAW, YOU AGREE THAT ANY DISPUTE RESOLUTION PROCEEDINGS WILL BE
                CONDUCTED ONLY ON AN INDIVIDUAL BASIS AND NOT IN A CLASS, CONSOLIDATED, OR REPRESENTATIVE ACTION. You waive
                any right to participate in a class action lawsuit or class-wide arbitration against Apex Cybernet.
                <br><br>
                <strong style="color:var(--text-main);">9.5 Time Limitation</strong><br>
                You agree that any claim arising out of or related to these Terms or the Platform must be filed within one (1)
                year after the cause of action arose, or the claim is permanently barred. This provision does not apply to
                claims where a longer limitation period is mandated by Philippine law.
            '],
            ['title' => '10. Governing Law', 'body' => '
                These Terms, your use of the Platform, and any dispute arising from or related thereto shall be governed by
                and construed in accordance with the laws of the Republic of the Philippines, without regard to its conflict
                of law provisions. The application of the United Nations Convention on Contracts for the International Sale
                of Goods (CISG) is expressly excluded.
            '],
            ['title' => '11. Third-Party Services and Links', 'body' => '
                <strong style="color:var(--text-main);">11.1 Third-Party Services</strong><br>
                The Platform may integrate with or depend upon third-party services including but not limited to payment
                processors (e-wallet providers), hosting providers, content delivery networks, and game platforms (Steam, Valve Corporation
                for Dota 2; Riot Games for Valorant; Smilegate for CrossFire). Apex Cybernet is not responsible for the availability,
                reliability, security, or policies of any third-party service.
                <br><br>
                <strong style="color:var(--text-main);">11.2 Third-Party Game Titles</strong><br>
                Dota 2 is a registered trademark of Valve Corporation. Valorant is a registered trademark of Riot Games, Inc.
                CrossFire is a registered trademark of Smilegate Entertainment Inc. These trademarks are used on the Platform
                solely for identification and descriptive purposes. Apex Cybernet is not affiliated with, sponsored by, or endorsed
                by any of these companies. All game-related content, artwork, and intellectual property remain the property of
                their respective owners.
                <br><br>
                <strong style="color:var(--text-main);">11.3 External Links</strong><br>
                The Platform may contain links to third-party websites or services that are not owned or controlled by Apex Cybernet.
                Apex Cybernet has no control over, and assumes no responsibility for, the content, privacy policies, or practices
                of any third-party websites or services. You acknowledge and agree that Apex Cybernet shall not be responsible or
                liable for any damage or loss caused by or in connection with the use of any such content, goods, or services
                available on or through any such websites or services.
            '],
            ['title' => '12. Force Majeure', 'body' => '
                Apex Cybernet shall not be liable for any failure or delay in performing its obligations under these Terms where
                such failure or delay results from a Force Majeure event. Force Majeure events include, without limitation:
                natural disasters (earthquakes, typhoons, floods), pandemics or epidemics, government actions or orders,
                civil unrest, war, terrorism, sanctions, embargoes, fire, explosion, power failure, internet or
                telecommunications outages, hardware or software failures, cyberattacks (including DDoS attacks, hacking, or
                data breaches), labor disputes, supply chain disruptions, or any other event beyond Apex Cybernet\'s reasonable
                control. In the event of a Force Majeure event, Apex Cybernet\'s obligations will be suspended for the duration
                of the event, and Apex Cybernet will use commercially reasonable efforts to resume performance as soon as
                practicable.
            '],
            ['title' => '13. Severability', 'body' => '
                If any provision of these Terms is held to be invalid, illegal, or unenforceable by a court of competent
                jurisdiction, such invalidity, illegality, or unenforceability shall not affect the remaining provisions of
                these Terms, which shall remain in full force and effect. The invalid, illegal, or unenforceable provision
                shall be modified to the minimum extent necessary to make it valid, legal, and enforceable while preserving
                its original intent.
            '],
            ['title' => '14. Entire Agreement', 'body' => '
                These Terms, together with the <a href="' . base_url('privacy.php') . '" style="color:var(--accent-light);">Privacy Policy</a>
                and <a href="' . base_url('rules.php') . '" style="color:var(--accent-light);">Tournament Rules</a>,
                constitute the entire agreement between you and Apex Cybernet regarding the use of the Platform and supersede all
                prior or contemporaneous understandings, agreements, negotiations, representations, and warranties, both
                written and oral, regarding the Platform. No amendment to or modification of these Terms shall be binding
                unless in writing and signed by Apex Cybernet or posted on the Platform.
            '],
            ['title' => '15. Waiver', 'body' => '
                The failure of Apex Cybernet to exercise or enforce any right or provision of these Terms shall not constitute a
                waiver of such right or provision. No waiver of any provision shall be deemed a further or continuing waiver
                of such provision or any other provision, and Apex Cybernet\'s failure to assert any right or provision under these
                Terms shall not constitute a waiver of such right or provision.
            '],
            ['title' => '16. Assignment', 'body' => '
                You may not assign or transfer these Terms or any rights or obligations hereunder, by operation of law or
                otherwise, without Apex Cybernet\'s prior written consent. Apex Cybernet may freely assign or transfer these Terms without
                restriction and without notice. Subject to the foregoing, these Terms shall bind and inure to the benefit of
                the parties, their successors, and permitted assigns.
            '],
            ['title' => '17. Electronic Communications', 'body' => '
                By using the Platform and/or creating an Account, you consent to receiving electronic communications from
                Apex Cybernet, including but not limited to on-Platform notifications, emails regarding your Account, and
                announcements regarding the Platform. You agree that all agreements, notices, disclosures, and other
                communications provided to you electronically satisfy any legal requirement that such communications be in
                writing. You acknowledge that Apex Cybernet may communicate with you via on-Platform notification systems, and
                it is your responsibility to regularly check your notifications.
            '],
            ['title' => '18. Survival', 'body' => '
                The following Sections shall survive any termination or expiration of these Terms: Sections 2 (Definitions),
                6 (Intellectual Property), 7 (Disclaimers and Limitation of Liability), 8 (Indemnification),
                9 (Dispute Resolution), 10 (Governing Law), 19 (User Representations and Warranties), 24 (Anti-Money
                Laundering), 25 (Confidentiality), 26 (Compliance with Local Laws), and any other provisions that by their
                nature are intended to survive termination.
            '],
            ['title' => '19. User Representations and Warranties', 'body' => '
                By using the Platform, you represent and warrant that:
                <br><br>
                (a) All information you provide to Apex Cybernet is truthful, accurate, current, and complete;<br>
                (b) You will maintain and promptly update such information to keep it truthful, accurate, current, and complete;<br>
                (c) You have the legal capacity and authority to enter into these Terms and to comply with all of your obligations
                hereunder;<br>
                (d) You are not located in, or a citizen or resident of, any jurisdiction in which the use of the Platform or its
                features would be prohibited by applicable law;<br>
                (e) Your use of the Platform will not violate any applicable law, regulation, rule, or ordinance in your
                jurisdiction;<br>
                (f) You have not been previously suspended, removed, or banned from the Platform;<br>
                (g) You are not creating an Account on behalf of someone who has been suspended, removed, or banned from the
                Platform;<br>
                (h) You will not use the Platform for any purpose that is unlawful, prohibited by these Terms, or in any manner
                inconsistent with these Terms;<br>
                (i) You are solely responsible for your own conduct and decisions while using the Platform.
                <br><br>
                Any breach of the foregoing representations and warranties may result in immediate termination of your Account,
                in addition to any other remedies available to Apex Cybernet under these Terms
                or applicable law.
            '],
            ['title' => '20. Platform Availability and Maintenance', 'body' => '
                <strong style="color:var(--text-main);">20.1 No Uptime Guarantee</strong><br>
                Apex Cybernet does not guarantee that the Platform will be available at all times or at any particular time. The
                Platform may be subject to interruptions, delays, outages, or errors for any reason, including but not limited
                to scheduled maintenance, unscheduled maintenance, system upgrades, server migrations, software updates,
                emergency repairs, hardware failures, network disruptions, and Force Majeure events.
                <br><br>
                <strong style="color:var(--text-main);">20.2 Scheduled Maintenance</strong><br>
                Apex Cybernet may perform scheduled maintenance on the Platform from time to time. While we will make reasonable
                efforts to provide advance notice of scheduled maintenance that may result in extended downtime, we are not
                obligated to do so. Maintenance windows may be scheduled at any time, including during tournament events.
                <br><br>
                <strong style="color:var(--text-main);">20.3 Emergency Maintenance</strong><br>
                Apex Cybernet may perform emergency maintenance at any time without prior notice in response to security threats,
                critical bugs, system failures, or other urgent situations that require immediate attention.
                <br><br>
                <strong style="color:var(--text-main);">20.4 Feature Changes</strong><br>
                Apex Cybernet reserves the right to modify, update, add, remove, or discontinue any feature, functionality, or
                content of the Platform at any time, with or without notice. This includes but is not limited to: the
                tournament bracket system, scheduling tools, notification
                system, and any other Platform feature. Apex Cybernet is not liable for any loss, inconvenience, or damage resulting
                from such changes.
                <br><br>
                <strong style="color:var(--text-main);">20.5 Data Loss</strong><br>
                While Apex Cybernet takes reasonable measures to protect data, we do not guarantee against data loss. Apex Cybernet is
                not responsible for any loss of data, including but not limited to Account data, transaction records, or
                user-generated content, that may occur due to system failures, maintenance,
                upgrades, security incidents, or any other cause.
            '],
            ['title' => '21. Beta Features and Experimental Services', 'body' => '
                <strong style="color:var(--text-main);">21.1 Beta Features</strong><br>
                Apex Cybernet may, from time to time, offer new features, tools, or services on the Platform on a beta, preview,
                experimental, or early access basis ("Beta Features"). Beta Features are provided "as is" and "as available"
                without any warranties of any kind. Beta Features may contain bugs, errors, or inaccuracies, and may not
                function as expected or described.
                <br><br>
                <strong style="color:var(--text-main);">21.2 No Obligation</strong><br>
                Apex Cybernet is under no obligation to: (a) continue offering any Beta Feature; (b) make any Beta Feature generally
                available; (c) fix bugs or errors in any Beta Feature; (d) provide support for any Beta Feature. Beta Features
                may be modified, suspended, or discontinued at any time without notice or liability.
                <br><br>
                <strong style="color:var(--text-main);">21.3 Feedback</strong><br>
                If you provide feedback, suggestions, ideas, or recommendations to Apex Cybernet regarding the Platform or any of its
                features ("Feedback"), you hereby grant Apex Cybernet a perpetual, irrevocable, worldwide, royalty-free, fully
                paid-up, non-exclusive, sublicensable, and transferable license to use, reproduce, modify, create derivative
                works from, distribute, publicly display, and otherwise exploit such Feedback for any purpose without
                compensation, attribution, or obligation to you. You acknowledge that Apex Cybernet is not obligated to use any
                Feedback and that Apex Cybernet may already be independently developing or may in the future independently develop
                ideas similar to your Feedback.
            '],
            ['title' => '22. Notifications and Communications', 'body' => '
                <strong style="color:var(--text-main);">22.1 Platform Notifications</strong><br>
                Apex Cybernet may send you notifications through the Platform\'s built-in notification system regarding your Account,
                tournaments, and other Platform-related matters. You
                agree that these notifications constitute adequate notice for all purposes under these Terms.
                <br><br>
                <strong style="color:var(--text-main);">22.2 Methods of Communication</strong><br>
                Apex Cybernet may communicate with you through: (a) on-Platform notification banners; (b) the notification bell
                system; (c) email to the address associated with your Account; (d) public announcements on the Platform;
                (e) any other method Apex Cybernet deems appropriate. It is your responsibility to ensure that your contact
                information is current and to regularly check your notifications.
                <br><br>
                <strong style="color:var(--text-main);">22.3 Deemed Receipt</strong><br>
                Any notice sent by Apex Cybernet through the Platform notification system or to your registered email address shall
                be deemed received by you: (a) in the case of on-Platform notifications, at the time the notification is
                posted; (b) in the case of email, twenty-four (24) hours after the email is sent, regardless of whether you
                actually read the notification or email.
                <br><br>
                <strong style="color:var(--text-main);">22.4 Notices to Apex Cybernet</strong><br>
                Any legal notices to Apex Cybernet must be submitted in writing through official channels. Notices sent through
                informal channels (such as social media messages or verbal communications) do not constitute valid legal notice.
            '],
            ['title' => '23. Relationship of the Parties', 'body' => '
                Nothing in these Terms shall be construed to create a partnership, joint venture, employment, agency, or
                franchise relationship between you and Apex Cybernet. You have no authority to bind Apex Cybernet in any way, including
                but not limited to making representations, warranties, or commitments on behalf of Apex Cybernet. You and Apex Cybernet
                are independent parties, and neither party has the power to bind the other or to incur obligations on behalf
                of the other. No User shall be considered an employee, agent, partner, or representative of Apex Cybernet by
                virtue of using the Platform.
            '],
            ['title' => '24. Anti-Money Laundering and Financial Crime', 'body' => '
                <strong style="color:var(--text-main);">24.1 Prohibited Use</strong><br>
                You shall not use the Platform for the purpose of money laundering, terrorist financing, tax evasion, fraud,
                or any other financial crime as defined under Philippine law, including but not limited to Republic Act No. 9160
                (Anti-Money Laundering Act of 2001, as amended) and Republic Act No. 10168 (Terrorism Financing Prevention and
                Suppression Act of 2012).
                <br><br>
                <strong style="color:var(--text-main);">24.2 Monitoring and Reporting</strong><br>
                Apex Cybernet reserves the right to monitor transactions and activities on the Platform for suspicious patterns,
                unusual behavior, or potential financial crime. Apex Cybernet may, at its sole discretion, freeze, suspend, or
                terminate Accounts and report suspicious activities to the Anti-Money Laundering Council (AMLC) or other
                relevant authorities without notice to the affected User.
                <br><br>
                <strong style="color:var(--text-main);">24.3 Cooperation</strong><br>
                You agree to cooperate with Apex Cybernet and any government authority in connection with any investigation
                related to financial crime or suspicious activity on the Platform. Failure to cooperate may result in
                immediate Account termination.
            '],
            ['title' => '25. Confidentiality', 'body' => '
                <strong style="color:var(--text-main);">25.1 Confidential Information</strong><br>
                Any non-public information disclosed by Apex Cybernet to you in connection with the Platform, including but not
                limited to proprietary algorithms, business strategies, unreleased features, internal policies, and
                administrative tools, constitutes confidential information. You agree not to disclose, publish, or
                disseminate such information to any third party without Apex Cybernet\'s prior written consent.
                <br><br>
                <strong style="color:var(--text-main);">25.2 Account Information</strong><br>
                You agree to keep your Account credentials, login information, and any administrative access (if granted)
                confidential. You shall not share screenshots, recordings, or descriptions of administrative tools,
                internal dashboards, or non-public Platform features without Apex Cybernet\'s prior written consent.
                <br><br>
                <strong style="color:var(--text-main);">25.3 Survival</strong><br>
                Your confidentiality obligations under this Section shall survive the termination of your Account and these
                Terms for a period of two (2) years.
            '],
            ['title' => '26. Compliance with Local Laws', 'body' => '
                You are solely responsible for compliance with all local, national, and international laws, rules, and
                regulations that apply to your use of the Platform, including but not limited to: (a) laws regarding online
                conduct and acceptable content; (b) laws regarding the export of data or software; (c) consumer protection
                laws; (d) tax laws applicable to any amounts received through the Platform, including tournament prizes.
                Apex Cybernet makes no representation that the Platform or its features
                are appropriate or available for use in all jurisdictions. Users who access the Platform from outside the
                Philippines do so at their own risk and are responsible for compliance with the laws of their own jurisdiction.
            '],
            ['title' => '27. Interpretation', 'body' => '
                <strong style="color:var(--text-main);">27.1 Headings</strong><br>
                The section headings in these Terms are for convenience of reference only and shall not affect the
                interpretation or construction of these Terms.
                <br><br>
                <strong style="color:var(--text-main);">27.2 Language</strong><br>
                These Terms are drafted in the English language. In the event of any conflict between the English version
                and any translation, the English version shall prevail.
                <br><br>
                <strong style="color:var(--text-main);">27.3 Construction</strong><br>
                In these Terms: (a) "including" means "including but not limited to"; (b) "or" is not exclusive unless
                the context clearly requires otherwise; (c) references to "Sections" refer to sections of these Terms;
                (d) words in the singular include the plural and vice versa; (e) references to "days" mean calendar days
                unless otherwise specified; (f) references to monetary amounts are in Philippine Pesos (₱) unless otherwise
                specified.
                <br><br>
                <strong style="color:var(--text-main);">27.4 No Third-Party Beneficiaries</strong><br>
                These Terms are intended solely for the benefit of you and Apex Cybernet and do not create any third-party
                beneficiary rights. No third party shall have any right to enforce any provision of these Terms.
            '],
            ['title' => '28. Changes to Terms', 'body' => '
                Apex Cybernet reserves the right to update, modify, supplement, or replace these Terms at any time
                at its sole discretion. Material changes will be communicated through on-Platform notifications, and the
                "Last updated" date at the top of this page will be revised. Continued use of the Platform after changes
                are posted constitutes your binding acceptance of the revised Terms, whether or not you have reviewed them.
                If you do not agree to the revised Terms, your sole remedy is to discontinue use of the Platform and delete
                your Account.
            '],
            ['title' => '29. Contact Information', 'body' => '
                For questions, concerns, or notices regarding these Terms, please contact us at:
                <br><br>
                <strong style="color:var(--text-main);">Apex Cybernet</strong><br>
                Cebu City, Philippines<br>
                <a href="' . base_url('contact.php') . '" style="color:var(--accent-light);">Contact form →</a>
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
            <a href="<?= base_url('privacy.php') ?>" style="color:var(--accent-light); text-decoration:none;">Privacy Policy</a>
            &nbsp;·&nbsp;
            <a href="<?= base_url('rules.php') ?>" style="color:var(--accent-light); text-decoration:none;">Tournament Rules</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
