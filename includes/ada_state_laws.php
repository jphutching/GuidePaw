<?php
declare(strict_types=1);

function adaStateNames(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
        'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
        'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
        'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];
}

function adaFederalLawProfile(): array
{
    return [
        'title' => 'Federal ADA baseline',
        'summary' => 'Under the ADA, a service animal is a dog individually trained to do work or perform tasks for a person with a disability. Staff may ask only the two ADA questions when the need is not obvious. Certification, registration papers, medical records, special ID, and task demonstrations are not required for public access.',
        'bullets' => [
            'Allowed questions: is the dog required because of a disability, and what work or task has the dog been trained to perform?',
            'Not allowed: diagnosis questions, medical records, certification demands, special ID requirements, or forced task demonstrations.',
            'A service animal may be excluded if it is out of control and not effectively corrected, or if it is not housebroken.',
            'The ADA does not recognize service animals in training for public access, but state law may provide access for training teams.',
        ],
        'source_label' => 'ADA.gov Service Animals FAQ',
        'source_url' => 'https://www.ada.gov/resources/service-animals-faqs/',
        'last_reviewed' => '2026-05-05',
    ];
}

function adaDefaultStateLawProfile(string $stateCode, string $stateName): array
{
    return [
        'state_code' => $stateCode,
        'state_name' => $stateName,
        'status' => 'pending',
        'summary' => 'GuidePaw has not yet added a reviewed state-specific summary for this state. Use the federal ADA baseline above and verify state or local rules through an official state source before relying on them.',
        'bullets' => [
            'Federal ADA public-access rules still apply in covered public places.',
            'State rules may differ for service animals in training, housing, penalties, schools, workplaces, transit, or misrepresentation.',
            'Use this as a reminder card, not legal advice.',
        ],
        'training_note' => 'State-specific service-dog-in-training rule not reviewed yet.',
        'housing_note' => 'Housing may involve separate federal and state reasonable-accommodation rules.',
        'source_label' => 'State law source pending review',
        'source_url' => '',
        'last_reviewed' => 'Pending',
    ];
}

function adaReviewedStateProfile(string $code, string $name, string $summary, array $bullets, string $trainingNote, string $housingNote, string $sourceLabel, string $sourceUrl): array
{
    return [
        'state_code' => $code,
        'state_name' => $name,
        'status' => 'reviewed',
        'summary' => $summary,
        'bullets' => $bullets,
        'training_note' => $trainingNote,
        'housing_note' => $housingNote,
        'source_label' => $sourceLabel,
        'source_url' => $sourceUrl,
        'last_reviewed' => '2026-05-05',
    ];
}

function adaStateLawProfiles(): array
{
    $profiles = [];
    foreach (adaStateNames() as $code => $name) {
        $profiles[$code] = adaDefaultStateLawProfile($code, $name);
    }

    $profiles['AZ'] = adaReviewedStateProfile(
        'AZ',
        'Arizona',
        'Arizona public-accommodation law defines a service animal as a dog or miniature horse that is individually trained, or in training, to do work or perform tasks for a person with a disability. It bars requiring identification for the service animal and allows only limited questions when the need is not obvious.',
        [
            'Arizona includes dogs and miniature horses that are trained or in training to perform disability-related work or tasks.',
            'Public accommodations may not require disability-related information or service-animal identification.',
            'Arizona law allows asking whether the animal is a service animal used because of disability and what work or task it is trained to perform.',
            'Emotional support, comfort, or companionship alone does not make an animal a service animal under the task-work standard.',
        ],
        'Arizona state language includes animals that are in training within the service-animal definition for public-access purposes.',
        'Housing may involve Arizona fair-housing rules plus federal FHA reasonable-accommodation rules; verify the housing-specific context before relying on this card.',
        'Arizona HB2255 / A.R.S. service animal public-place language',
        'https://www.azleg.gov/legtext/52leg/2r/laws/0099.htm'
    );

    $profiles['CA'] = adaReviewedStateProfile(
        'CA',
        'California',
        'California uses a task-based definition for service animals in the cited food-code section, including dogs individually trained to do disability-related work or tasks, or dogs in training to do that work or those tasks. California public resources also emphasize that registration or certification is not required under the ADA framework.',
        [
            'California Health and Safety Code section 113903 includes dogs trained or in training to do disability-related work or tasks.',
            'The state definition does not include other species of animals in that section.',
            'Emotional support, comfort, companionship, or crime deterrence alone does not meet the task-work standard in that section.',
            'California Department of Rehabilitation guidance notes that registration or certification is not required and that the ADA limits staff to two core questions.',
        ],
        'California recognizes service animals in training in the cited statutory definition, but training access can depend on the specific setting and applicable California civil-rights rules.',
        'Housing may involve California fair-housing rules plus federal FHA reasonable-accommodation rules, which are separate from public-access rules.',
        'California Health & Safety Code § 113903 / CA Department of Rehabilitation',
        'https://dor.ca.gov/Home/SupportAnimals'
    );

    $profiles['CO'] = adaReviewedStateProfile(
        'CO',
        'Colorado',
        'Colorado law gives qualified individuals with disabilities the right to be accompanied by a trained service animal without an extra charge in employment, housing, public accommodations, public-entity programs, public transportation, and other places open to the public. Colorado also gives trainers and people with disabilities accompanied by animals being trained as service animals similar access without an extra charge.',
        [
            'Colorado covers public accommodations, public-entity programs, public transportation, employment, housing, and other places open to the public.',
            'Colorado separately recognizes access for a trainer of a service animal and for an individual with a disability accompanied by an animal being trained as a service animal.',
            'Colorado bars extra charges for the service animal or service animal in training in the covered places and activities.',
            'Colorado common-carrier language may require a service animal in training to be visibly and prominently identified as in training.',
        ],
        'Colorado service-animal-in-training access is broader than the federal ADA baseline, but transportation rules may add visible-identification requirements for animals in training.',
        'Colorado housing access is included in the cited service-animal rights statute, but housing disputes may also involve federal FHA accommodation rules.',
        'Colorado Revised Statutes § 24-34-803',
        'https://law.justia.com/codes/colorado/title-24/principal-departments/article-34/part-8/section-24-34-803/'
    );

    $profiles['ID'] = adaReviewedStateProfile(
        'ID',
        'Idaho',
        'Idaho law requires public accommodations to modify policies to permit service dogs for individuals with disabilities or authorized handlers. Idaho also separately protects access for dogs-in-training, with control, grooming, liability, and visible-identification requirements.',
        [
            'Idaho public accommodations must permit service dogs used by individuals with disabilities or authorized handlers.',
            'Idaho allows exclusion when the service dog is out of control and not effectively corrected, or is not housebroken.',
            'A person may not be denied public accommodation or public transportation access because they are accompanied by a dog-in-training.',
            'Idaho dogs-in-training must be leashed/controlled and visually identified as dogs-in-training.',
        ],
        'Idaho gives access for dogs-in-training, but the dog must be controlled, properly leashed, and visually identified as a dog-in-training.',
        'Housing may involve Idaho public-accommodation/service-dog rules plus federal FHA reasonable-accommodation rules; verify the housing context before relying on this card.',
        'Idaho Code §§ 56-704A and 18-5812B',
        'https://law.justia.com/codes/idaho/title-18/chapter-58/section-18-5812b/'
    );

    $profiles['KS'] = adaReviewedStateProfile(
        'KS',
        'Kansas',
        'Kansas law gives professional trainers from recognized training centers access rights while training assistance dogs in the listed public places, without an extra charge. Kansas also has assistance-dog public-access protections for people with disabilities, while federal ADA rules still apply in covered public accommodations.',
        [
            'Kansas allows a professional trainer from a recognized training center to be accompanied by an assistance dog while training in listed public places.',
            'The trainer is liable for damage done to the premises or facilities by the dog.',
            'Kansas public materials distinguish assistance dogs from comfort or emotional-support animals that are not trained for disability-related work.',
            'Federal ADA rules still control covered public accommodations and limit required documentation/certification demands.',
        ],
        'Kansas service-dog-in-training access is narrower than some states: the cited training-access statute applies to professional trainers from recognized training centers.',
        'Housing may involve separate federal FHA accommodation rules and Kansas-specific housing issues; this profile focuses on public access/training access.',
        'Kansas Statutes § 39-1109',
        'https://www.kslegislature.gov/li/b2025_26/statute/039_000_0000_chapter/039_011_0000_article/039_011_0009_section/039_011_0009_k/'
    );

    $profiles['MT'] = adaReviewedStateProfile(
        'MT',
        'Montana',
        'Montana law gives a person with a disability the right to be accompanied by a service animal, and recognizes access for service animals in training with visible identification. A person training a service animal has the same rights and responsibilities as the person with a disability under the cited section.',
        [
            'Montana recognizes access for a service animal or service animal in training in listed public places without extra charge.',
            'A person training a service animal has the same rights and responsibilities granted in the cited section.',
            'A service animal in training must wear visible, legible written identification on a leash, collar, cape, harness, or backpack.',
            'The handler/person is liable for damage done by the animal.',
        ],
        'Montana service-animal-in-training access includes a visible-identification requirement readable from a distance under the cited statute.',
        'Montana housing access is included for people with disabilities who have or obtain a service animal, with no extra compensation but liability for damage.',
        'Montana Code § 49-4-214',
        'https://law.justia.com/codes/montana/title-49/chapter-4/part-2/section-49-4-214/'
    );

    $profiles['NE'] = adaReviewedStateProfile(
        'NE',
        'Nebraska',
        'Nebraska law gives a person with a disability full and equal access to public places and public accommodations, and the right to be accompanied by a service animal. It also gives a bona fide trainer of a service animal the right to be accompanied by that animal in training in the listed places without an extra charge.',
        [
            'Nebraska covers streets, public buildings, public facilities, public places, common carriers, lodging, places of public accommodation, amusement, or resort, and places to which the general public is invited.',
            'A person with a disability has the right to be accompanied by a service animal in the covered places.',
            'A bona fide trainer of a service animal has access rights with the animal in training in the same listed places.',
            'The person is liable for damage done by the animal to premises, facilities, or a person.',
        ],
        'Nebraska service-animal-in-training access applies to a bona fide trainer of a service animal in the listed public places.',
        'Nebraska housing statute gives a person with a disability full and equal access to housing accommodations with a service animal and bars extra deposits, while allowing liability for damage.',
        'Nebraska Revised Statutes §§ 20-127 and 20-131.04',
        'https://nebraskalegislature.gov/laws/statutes.php?statute=20-127'
    );

    $profiles['NM'] = adaReviewedStateProfile(
        'NM',
        'New Mexico',
        'New Mexico law requires that a person with a disability using a qualified service animal be admitted to buildings open to the public, public accommodations, and common carriers when the animal is under the control of an owner, trainer, or handler. New Mexico also prohibits certain interference with qualified service animals.',
        [
            'New Mexico protects access to buildings open to the public, public accommodations, and common carriers for a person with a disability using a qualified service animal.',
            'The qualified service animal must be under the control of an owner, trainer, or handler.',
            'Entry may not be denied because of a no-pets policy when the animal qualifies under the statute.',
            'New Mexico has separate language prohibiting intentional interference with the use of a qualified service animal.',
        ],
        'New Mexico’s access statute references control by an owner, trainer, or handler; verify specific service-animal-in-training scenarios against current state law and setting-specific rules.',
        'Housing may involve New Mexico law plus federal FHA reasonable-accommodation rules; this profile focuses on public access and common carrier language.',
        'New Mexico Statutes §§ 28-11-3 and 28-11-5',
        'https://law.justia.com/codes/new-mexico/chapter-28/article-11/section-28-11-3/'
    );

    $profiles['NV'] = adaReviewedStateProfile(
        'NV',
        'Nevada',
        'Nevada law recognizes service animals and service animals in training in public accommodations. It bars refusal of admittance or service because a person is accompanied by a service animal or because a person is training a service animal, bars extra fees or deposits, and bars requiring proof that the animal is a service animal or in training.',
        [
            'Nevada bars refusing admittance or service because a person is accompanied by a service animal.',
            'Nevada also bars refusing admittance or service to a person training a service animal.',
            'Nevada bars additional fees or deposits as a condition of access for a service animal or service animal in training.',
            'Nevada permits asking whether the animal is a service animal or service animal in training and what tasks it performs or is being trained to perform.',
        ],
        'Nevada expressly recognizes service animals in training in public-accommodation access language.',
        'Nevada housing language includes service animals and service animals in training, while allowing liability for damage in applicable circumstances.',
        'Nevada NRS Chapter 426 service-animal provisions',
        'https://www.leg.state.nv.us/statutes/73rd/Stats200507.html'
    );

    $profiles['OK'] = adaReviewedStateProfile(
        'OK',
        'Oklahoma',
        'Oklahoma public-accommodation law uses the federal ADA definitions for public accommodation and service animal, excludes emotional support and therapy animals from service-animal status, and directs public accommodations that ask qualification questions to comply with the federal ADA inquiry rule.',
        [
            'Oklahoma public-accommodation law uses 28 C.F.R. § 36.104 definitions for public accommodation and service animal.',
            'Oklahoma excludes emotional support animals and therapy animals from the service-animal definition in the cited public-accommodation section.',
            'If a public accommodation asks about service-animal qualification, it must comply with the federal ADA question limits.',
            'Oklahoma has 2025 misrepresentation language with an effective date of November 1, 2025 in the cited statute source.',
        ],
        'Oklahoma public-accommodation statute does not appear to create a broad service-animal-in-training access right in the cited public-accommodation section; use federal ADA baseline unless another setting-specific rule applies.',
        'Oklahoma has a separate assistance-animal housing accommodation statute that includes service animals and emotional support animals in reasonable-accommodation requests.',
        'Oklahoma Statutes Title 4 § 801 and Title 41 § 113.2',
        'https://law.justia.com/codes/oklahoma/title-4/section-4-801/'
    );

    $profiles['OR'] = adaReviewedStateProfile(
        'OR',
        'Oregon',
        'Oregon law uses “assistance animal” and “assistance animal trainee” language for public accommodations and access to state government services, programs, or activities. Oregon bars asking about the nature or extent of disability and bars requiring documentation proving an animal is an assistance animal or trainee.',
        [
            'Oregon defines an assistance animal trainee as an animal undergoing development and training to do disability-related work or tasks.',
            'Oregon covers places of public accommodation and access to state government services, programs, or activities.',
            'Oregon bars asking about the nature or extent of a disability.',
            'Oregon bars requiring documentation proving that an animal is an assistance animal or assistance animal trainee.',
        ],
        'Oregon provides specific public-accommodation/state-government access language for assistance animal trainees and trainers.',
        'Housing may involve Oregon-specific housing rules plus federal FHA reasonable-accommodation rules; this card focuses on public accommodation/state-government access.',
        'ORS 659A.143 Assistance animals',
        'https://oregon.public.law/statutes/ors_659A.143'
    );

    $profiles['TX'] = adaReviewedStateProfile(
        'TX',
        'Texas',
        'Texas public guidance says state law protects access for trained service animals in public places and recognizes that a service animal in training must not be denied admittance to a public facility when accompanied by an approved trainer. Texas law also limits demands or inquiries about qualifications or certifications.',
        [
            'Texas state guidance says a person with a disability has the right to use a trained service animal in public places.',
            'A service animal in training must not be denied admittance to a public facility when accompanied by an approved trainer.',
            'Texas bars demands or inquiries about qualifications or certifications except to determine the basic type of assistance provided.',
            'When disability is not readily apparent, Texas guidance lists the two familiar ADA-style questions.',
        ],
        'Texas training access depends on accompaniment by an approved trainer under the state guidance summarized here.',
        'Texas housing may involve separate state and federal fair-housing rules; the Texas Governor’s guidance notes FHA accommodation issues for assistance animals.',
        'Texas Governor’s Office — Disability Law, Service Animals',
        'https://gov.texas.gov/organization/disabilities/assistance_animals'
    );

    $profiles['UT'] = adaReviewedStateProfile(
        'UT',
        'Utah',
        'Utah law gives an individual with a disability the right to be accompanied by a service animal in listed public places without an additional charge, unless exclusion is permitted under federal law. Utah also gives access rights to an individual who is training an animal to become a service animal in those places, without an additional charge.',
        [
            'Utah recognizes public-access rights for a person with a disability accompanied by a service animal.',
            'Utah separately recognizes access for an individual training an animal to become a service animal.',
            'Utah allows recovery of reasonable repair costs for damage caused by the animal.',
            'Exclusion remains possible where permitted under federal law, including danger, nuisance, out-of-control behavior, or housebreaking issues.',
        ],
        'Utah service-animal-in-training access is broader than the federal ADA baseline. Training access still depends on behavior, control, and the places covered by Utah law.',
        'Utah housing language bars extra fees or deposits for a service animal or support animal, while allowing recovery of reasonable repair costs for damage.',
        'Utah Code § 26B-6-803',
        'https://le.utah.gov/xcode/Title26B/Chapter6/C26B-6-S803_2023050320230503.pdf'
    );

    $profiles['WA'] = adaReviewedStateProfile(
        'WA',
        'Washington',
        'Washington public-accommodation resources recognize trained service animals and note that state law has expanded to include service animals in training. Washington Human Rights Commission materials point users to public-accommodation guidance and the new service-animal-in-training changes.',
        [
            'Washington public-accommodation guidance covers use of trained dog guides and service animals by disabled persons.',
            'Washington Human Rights Commission materials note new law changes expanding to service animals in training.',
            'Washington campus public resources state that service animals in training are permitted in spaces of public accommodation and documentation is not required in those spaces.',
            'Employment settings can be different from public accommodation settings for service animals in training.',
        ],
        'Washington service-animal-in-training access appears broader in public-accommodation spaces, but employment settings may need separate accommodation or policy review.',
        'Housing may involve separate Washington and federal fair-housing accommodation rules; this card focuses on public-accommodation notes.',
        'Washington State Human Rights Commission — Service Animals',
        'https://www.hum.wa.gov/public-accommodation-pa/use-trained-service-animal-pa'
    );

    $profiles['WY'] = adaReviewedStateProfile(
        'WY',
        'Wyoming',
        'Wyoming law gives people with disabilities equal use of public places and access to public accommodations. Wyoming defines service animal by reference to federal ADA regulations and includes a dog that is being trained to do work or perform tasks for an individual with a disability.',
        [
            'Wyoming protects equal access to public buildings, public facilities, public places, and places of public accommodation.',
            'Wyoming uses federal ADA service-animal definitions and includes service miniature horses under the cited federal regulations.',
            'Wyoming’s service-animal definition includes a dog being trained to do work or perform tasks for a person with a disability.',
            'Wyoming housing language references assistance animals and federal Fair Housing Act treatment for leased or rented residential property.',
        ],
        'Wyoming includes dogs in training within its service-animal definition, but access still depends on the covered setting and behavior/control rules.',
        'Wyoming residential-property language references assistance animals and the federal Fair Housing Act, and notes liability for damage by the assistance animal.',
        'Wyoming Statutes §§ 35-13-201 and 35-13-205',
        'https://law.justia.com/codes/wyoming/title-35/chapter-13/article-2/section-35-13-205/'
    );

    return $profiles;
}
