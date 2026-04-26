<?php

if (!function_exists('getMicrochipResourceLinks')) {
function getMicrochipResourceLinks(): array {
    return [
        ['label' => 'BuddyID', 'type' => 'register', 'url' => 'https://buddyid.com/', 'note' => 'BuddyBadge account and chip-linked lost-pet tools'],
        ['label' => 'Free Pet Chip Registry', 'type' => 'register', 'url' => 'https://www.freepetchipregistry.com/', 'note' => 'Free-for-life registration for many chip brands'],
        ['label' => 'PetLink Register', 'type' => 'register', 'url' => 'https://www.petlink.net/account/register-pet/', 'note' => 'Register or update many PetLink-backed chips'],
        ['label' => 'AAHA Lookup', 'type' => 'lookup', 'url' => 'https://www.aaha.org/for-veterinary-professionals/microchip-registry-lookup-tool-aaha-find-your-pets-microchip-registry/', 'note' => 'Find which registry currently holds the chip record'],
        ['label' => 'PetLink Search', 'type' => 'lookup', 'url' => 'https://www.petlink.net/microchip-search/', 'note' => 'Check whether a chip is already in PetLink'],
    ];
}

function getBeneficialTrainingPrograms(): array {
    return [
        ['name' => 'AKC S.T.A.R. Puppy', 'code' => 'akc_star_puppy', 'best_for' => 'Young puppies and new adolescent dogs needing socialization and owner basics.', 'notes' => 'Helpful first milestone before CGC. Focuses on socialization, training, activity, and responsible ownership.'],
        ['name' => 'AKC Canine Good Citizen (CGC)', 'code' => 'akc_cgc', 'best_for' => 'Baseline manners, leash control, and polite public behavior.', 'notes' => 'Good benchmark before heavier public-access work.'],
        ['name' => 'AKC Community Canine (CGCA)', 'code' => 'akc_cgca', 'best_for' => 'Dogs already solid on CGC skills that need busier community proofing.', 'notes' => 'Advanced CGC in more real-life settings.'],
        ['name' => 'AKC Urban CGC (CGCU)', 'code' => 'akc_urban', 'best_for' => 'Dogs working around sidewalks, parking lots, retail, travel, and truck-stop style environments.', 'notes' => 'Strong fit for service-dog public-access prep and OTR life.'],
        ['name' => 'AKC Temperament Test (ATT)', 'code' => 'akc_att', 'best_for' => 'Candidate screening for nerve, recovery, stability, and breed-typical temperament.', 'notes' => 'Useful information for prospect selection, but not a service-dog certification by itself.'],
        ['name' => 'AKC Trick Dog', 'code' => 'akc_trick', 'best_for' => 'Confidence building, engagement, body awareness, and handler timing.', 'notes' => 'Useful for timid dogs and for building training momentum.'],
        ['name' => 'AKC Virtual Home Manners', 'code' => 'akc_vhm', 'best_for' => 'Dogs that need stronger in-home control before public work.', 'notes' => 'Good stepping stone when home manners are the bottleneck.'],
        ['name' => 'Internal Public Access Benchmark', 'code' => 'public_access_benchmark', 'best_for' => 'Service-dog teams tracking readiness for longer outings and public access.', 'notes' => 'Internal checklist only, not a government-issued certification.'],
        ['name' => 'Service Dog Candidate Screen', 'code' => 'candidate_screen', 'best_for' => 'Choosing or evaluating a prospect before investing in heavy task specialization.', 'notes' => 'Look at recovery, neutrality, resilience, work drive, and handler fit first.'],
    ];
}

function getTrainingProgramTemplate(): array {
    return [
        'Service Dog Candidate Screen' => [
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Neutrality to friendly strangers', 'description' => 'Shows interest or calm neutrality without frantic solicitation or avoidance.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Recovery after startling sound', 'description' => 'May notice a dropped item or sudden noise, but recovers quickly and can re-engage.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Surface confidence', 'description' => 'Can move across new flooring, grates, mats, and mild elevation changes with coaching.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Handling tolerance', 'description' => 'Accepts collar handling, paws, light restraint, and basic care without major stress.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Dog neutrality', 'description' => 'Can observe other dogs without fixating, melting down, or escalating.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Food or toy engagement', 'description' => 'Shows workable motivation for rewards, play, or handler interaction.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Vehicle and crate settle', 'description' => 'Can relax in transport or confinement without prolonged panic.'],
            ['level' => 1, 'track' => 'candidate_screen', 'item_name' => 'Bounce-back after frustration', 'description' => 'Regains composure after a failed rep or mild frustration instead of spiraling.'],
        ],
        'AKC S.T.A.R. Puppy' => [
            ['level' => 1, 'track' => 'akc_star_puppy', 'item_name' => 'Socialization plan', 'description' => 'Puppy is being exposed safely to people, surfaces, sounds, and novelty.'],
            ['level' => 1, 'track' => 'akc_star_puppy', 'item_name' => 'Owner basics and handling', 'description' => 'Handler is practicing gentle handling, grooming prep, and daily routines.'],
            ['level' => 1, 'track' => 'akc_star_puppy', 'item_name' => 'Puppy basics: sit / down / come', 'description' => 'Early foundation cues are introduced with short positive sessions.'],
            ['level' => 1, 'track' => 'akc_star_puppy', 'item_name' => 'Leash introduction and following', 'description' => 'Puppy is learning to move with the handler without panic or constant opposition.'],
        ],
        'Foundation Obedience' => [
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Name response / check-in', 'description' => 'Dog orients to the handler quickly when called by name.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Sit', 'description' => 'Prompt and reliable sit from standing or motion.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Down', 'description' => 'Prompt down on cue in calm locations.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Stay', 'description' => 'Short duration stay with handler nearby.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Recall / come', 'description' => 'Returns directly to handler when called.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Loose leash heel', 'description' => 'Walks with low leash pressure in low distraction settings.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Leave it', 'description' => 'Disengages from food, objects, or distractions when cued.'],
            ['level' => 1, 'track' => 'obedience', 'item_name' => 'Watch me / focus', 'description' => 'Offers sustained eye contact when cued.'],
        ],
        'Intermediate Control' => [
            ['level' => 2, 'track' => 'obedience', 'item_name' => 'Duration stay', 'description' => 'Maintains stay for longer intervals.'],
            ['level' => 2, 'track' => 'obedience', 'item_name' => 'Distance commands', 'description' => 'Responds to sit/down/stay from increasing distance.'],
            ['level' => 2, 'track' => 'obedience', 'item_name' => 'Place / mat work', 'description' => 'Moves to a defined place and settles there.'],
            ['level' => 2, 'track' => 'public_access', 'item_name' => 'Door manners', 'description' => 'Waits calmly at doors and thresholds.'],
            ['level' => 2, 'track' => 'public_access', 'item_name' => 'Settle in busy environment', 'description' => 'Can settle calmly around moderate distractions.'],
            ['level' => 2, 'track' => 'public_access', 'item_name' => 'Ignore dropped food', 'description' => 'Maintains handler focus around food temptation.'],
        ],
        'AKC Canine Good Citizen' => [
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Accepting a friendly stranger', 'description' => 'Approaches calmly without overreaction.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Sitting politely for petting', 'description' => 'Allows petting while staying under control.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Appearance and grooming', 'description' => 'Accepts handling of ears, feet, and brushing.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Out for a walk', 'description' => 'Loose-leash walking with turns and pace changes.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Walking through a crowd', 'description' => 'Remains composed through a small crowd.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Sit / down / stay', 'description' => 'Performs sit, down, and stay on cue.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Coming when called', 'description' => 'Returns promptly to handler.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Reaction to another dog', 'description' => 'Passes another dog calmly.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Reaction to distraction', 'description' => 'Recovers from environmental distraction.'],
            ['level' => 3, 'track' => 'akc_cgc', 'item_name' => 'Supervised separation', 'description' => 'Tolerates brief separation from handler.'],
        ],
        'Advanced Public Access' => [
            ['level' => 3, 'track' => 'public_access', 'item_name' => 'Public neutrality', 'description' => 'Ignores greetings, noises, carts, and dog traffic.'],
            ['level' => 3, 'track' => 'public_access', 'item_name' => 'Under-table settle', 'description' => 'Settles tucked safely under table or chair.'],
            ['level' => 3, 'track' => 'public_access', 'item_name' => 'Vehicle entry / exit control', 'description' => 'Enters and exits vehicle only when released.'],
            ['level' => 3, 'track' => 'public_access', 'item_name' => 'Automatic doors / elevators', 'description' => 'Moves calmly through automatic doors and elevators.'],
            ['level' => 3, 'track' => 'public_access', 'item_name' => 'Crowd navigation', 'description' => 'Maintains position moving through stores, lobbies, or truck stops.'],
        ],
        'AKC Community Canine / Urban CGC' => [
            ['level' => 4, 'track' => 'akc_cgca', 'item_name' => 'CGCA community proofing', 'description' => 'Works politely in parks, sidewalks, or busier public spaces without food rewards.'],
            ['level' => 4, 'track' => 'akc_cgca', 'item_name' => 'CGCA real-world distractions', 'description' => 'Handles carts, doors, benches, and mild bustle while remaining responsive.'],
            ['level' => 4, 'track' => 'akc_urban', 'item_name' => 'Urban sidewalk / curb skills', 'description' => 'Can stop, wait, and navigate curb transitions and tighter pedestrian spaces.'],
            ['level' => 4, 'track' => 'akc_urban', 'item_name' => 'Urban food / litter refusal', 'description' => 'Ignores food, wrappers, and sidewalk temptations during movement.'],
        ],
        'AKC Temperament / Confidence' => [
            ['level' => 4, 'track' => 'akc_att', 'item_name' => 'Novel sight recovery', 'description' => 'Can notice umbrella pops, odd visual objects, or unusual motion and recover appropriately.'],
            ['level' => 4, 'track' => 'akc_att', 'item_name' => 'Auditory recovery', 'description' => 'Handles sound surprises without prolonged shutdown or escalation.'],
            ['level' => 4, 'track' => 'akc_att', 'item_name' => 'Environmental nerve check', 'description' => 'Shows stable temperament across surfaces, spaces, and controlled challenges.'],
            ['level' => 4, 'track' => 'akc_trick', 'item_name' => 'Confidence trick chain', 'description' => 'Uses trick work to improve engagement, body awareness, and optimism.'],
            ['level' => 4, 'track' => 'akc_vhm', 'item_name' => 'Home manners reliability', 'description' => 'Strong home manners are in place before relying on public behavior.'],
        ],
        'Service Dog Task Work' => [
            ['level' => 4, 'track' => 'service_task', 'item_name' => 'Primary task foundation', 'description' => 'Task is clearly defined and introduced with low distraction.'],
            ['level' => 4, 'track' => 'service_task', 'item_name' => 'Task under moderate distraction', 'description' => 'Primary task remains reliable outside the home.'],
            ['level' => 4, 'track' => 'service_task', 'item_name' => 'Retrieval / bring', 'description' => 'Retrieves named item to hand or lap when relevant.'],
            ['level' => 4, 'track' => 'service_task', 'item_name' => 'Alert / interruption behavior', 'description' => 'Interrupts or alerts consistently when the trained cue appears.'],
            ['level' => 4, 'track' => 'service_task', 'item_name' => 'Deep pressure / grounding', 'description' => 'Positions calmly and with duration if this is a trained task.'],
        ],
        'Internal Public Access Benchmark' => [
            ['level' => 5, 'track' => 'public_access_benchmark', 'item_name' => 'Restaurant / waiting room settle', 'description' => 'Can tuck in and remain unobtrusive for an extended stay.'],
            ['level' => 5, 'track' => 'public_access_benchmark', 'item_name' => 'Crowd and narrow-space navigation', 'description' => 'Moves through tight or crowded public areas while remaining under control.'],
            ['level' => 5, 'track' => 'public_access_benchmark', 'item_name' => 'Long outing stamina', 'description' => 'Maintains behavior quality over longer public sessions, not just short wins.'],
            ['level' => 5, 'track' => 'public_access_benchmark', 'item_name' => 'Task reliability in public', 'description' => 'Trained task remains accurate and accessible during outings.'],
        ],
        'Working-Dog Foundations' => [
            ['level' => 4, 'track' => 'working_foundation', 'item_name' => 'Directional send-outs', 'description' => 'Moves to cone, place, or target on cue without bite/protection work.'],
            ['level' => 4, 'track' => 'working_foundation', 'item_name' => 'Target indication / scent intro', 'description' => 'Begins indication behavior for nose work or search games.'],
            ['level' => 4, 'track' => 'working_foundation', 'item_name' => 'Search pattern foundations', 'description' => 'Builds controlled, handler-led search patterns.'],
            ['level' => 4, 'track' => 'working_foundation', 'item_name' => 'Clarity under distance', 'description' => 'Responds to commands when separated from the handler.'],
        ],
        'Reliability and Maintenance' => [
            ['level' => 5, 'track' => 'maintenance', 'item_name' => 'Task reliability in high distraction', 'description' => 'Maintains trained task response in difficult settings.'],
            ['level' => 5, 'track' => 'maintenance', 'item_name' => 'Long duration public access', 'description' => 'Settles and works through long outings.'],
            ['level' => 5, 'track' => 'maintenance', 'item_name' => 'Road routine consistency', 'description' => 'Handles OTR transitions, stops, and rest periods reliably.'],
            ['level' => 5, 'track' => 'maintenance', 'item_name' => 'Handler transition / secondary handler', 'description' => 'Can work appropriately with approved secondary handlers.'],
        ],
    ];
}
}
