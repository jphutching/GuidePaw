<?php

function getDogBreedsCatalog(): array
{
    $catalog = [
        'Labrador Retriever' => [
            'group' => 'Sporting',
            'temperament' => 'Friendly, steady, eager to please',
            'traits' => 'Highly trainable, food motivated, social, adaptable to public work',
            'notes' => 'Often strong service-dog candidates. Can be energetic when young and need regular exercise to stay settled.',
        ],
        'Golden Retriever' => [
            'group' => 'Sporting',
            'temperament' => 'Gentle, patient, affectionate',
            'traits' => 'Handler-focused, soft temperament, biddable, commonly successful in public access work',
            'notes' => 'Usually excellent with people. Coat care is heavier than short-coated breeds.',
        ],
        'German Shepherd Dog' => [
            'group' => 'Herding',
            'temperament' => 'Loyal, alert, confident',
            'traits' => 'Intelligent, responsive, protective tendencies, thrives with structure',
            'notes' => 'Can excel in service work with solid socialization. Needs careful training to avoid over-guarding or environmental sensitivity.',
        ],
        'Standard Poodle' => [
            'group' => 'Non-Sporting',
            'temperament' => 'Bright, attentive, sensitive',
            'traits' => 'Very trainable, athletic, often good for low-shed households',
            'notes' => 'Common service-dog choice. Regular grooming is a must.',
        ],
        'Bernese Mountain Dog' => [
            'group' => 'Working',
            'temperament' => 'Calm, affectionate, steady',
            'traits' => 'Large frame, people-oriented, generally gentle disposition',
            'notes' => 'Can suit mobility-style work, but heat tolerance and joint longevity should be watched closely.',
        ],
        'Great Dane' => [
            'group' => 'Working',
            'temperament' => 'Patient, friendly, composed',
            'traits' => 'Tall, easygoing, often naturally noticeable in public',
            'notes' => 'Can support some mobility-adjacent roles, but short lifespan and orthopedic issues are important considerations.',
        ],
        'Boxer' => [
            'group' => 'Working',
            'temperament' => 'Playful, loyal, energetic',
            'traits' => 'People-focused, animated, intelligent, can be goofy and strong',
            'notes' => 'May do well with experienced handlers. Needs impulse control and enough exercise.',
        ],
        'Doberman Pinscher' => [
            'group' => 'Working',
            'temperament' => 'Devoted, alert, responsive',
            'traits' => 'Fast learner, highly bonded, confident, athletic',
            'notes' => 'Can be excellent in trained hands, but public perception and guarding instincts require careful neutrality work.',
        ],
        'Rottweiler' => [
            'group' => 'Working',
            'temperament' => 'Calm, confident, devoted',
            'traits' => 'Stable with good breeding, powerful, trainable, deeply bonded',
            'notes' => 'Needs strong socialization and polished public manners. Public bias can be a factor.',
        ],
        'Newfoundland' => [
            'group' => 'Working',
            'temperament' => 'Sweet, calm, patient',
            'traits' => 'Large, gentle, slow-moving, often tolerant and people-friendly',
            'notes' => 'Can be comforting and steady, though size, drool, and heat sensitivity matter.',
        ],
        'Border Collie' => [
            'group' => 'Herding',
            'temperament' => 'Intense, responsive, energetic',
            'traits' => 'Extremely trainable, quick learner, high mental drive',
            'notes' => 'Brilliant but often more intensity than many service handlers want for daily public access.',
        ],
        'Australian Shepherd' => [
            'group' => 'Herding',
            'temperament' => 'Smart, loyal, energetic',
            'traits' => 'Fast learner, engaged, athletic, can be environmentally aware',
            'notes' => 'Can do well with active handlers, but sensitivity and herding tendencies may need extra shaping.',
        ],
        'Collie' => [
            'group' => 'Herding',
            'temperament' => 'Gentle, responsive, devoted',
            'traits' => 'Soft expression, handler-aware, trainable, often naturally attentive',
            'notes' => 'Can make solid service dogs with good confidence-building. Coat care varies by type.',
        ],
        'Shetland Sheepdog' => [
            'group' => 'Herding',
            'temperament' => 'Sensitive, bright, loyal',
            'traits' => 'Very trainable, tuned in to handler, compact size',
            'notes' => 'May fit small-dog task work, but some lines can be vocal or noise-sensitive.',
        ],
        'Corgi' => [
            'group' => 'Herding',
            'temperament' => 'Bold, clever, outgoing',
            'traits' => 'Portable, trainable, sturdy, expressive personality',
            'notes' => 'Can handle alert/response tasks well, but body structure limits mobility-style work.',
        ],
        'Belgian Malinois' => [
            'group' => 'Herding',
            'temperament' => 'Driven, intense, highly alert',
            'traits' => 'Exceptionally trainable, fast, high work ethic, environmentally sharp',
            'notes' => 'Usually too much dog for most service work homes unless the handler is very experienced and the dog is an unusually stable candidate.',
        ],
        'Great Pyrenees' => [
            'group' => 'Working',
            'temperament' => 'Calm, independent, watchful',
            'traits' => 'Large, steady, patient, slower decision style',
            'notes' => 'Can be emotionally steady, but independence can reduce precision and responsiveness for task work.',
        ],
        'Saint Bernard' => [
            'group' => 'Working',
            'temperament' => 'Gentle, calm, patient',
            'traits' => 'Very large, tolerant, affectionate',
            'notes' => 'Can be comforting and visible in public, but size, drool, and orthopedic limits matter.',
        ],
        'Mastiff' => [
            'group' => 'Working',
            'temperament' => 'Dignified, calm, protective',
            'traits' => 'Massive build, usually low energy, strong bonding',
            'notes' => 'Can be steady but often not as biddable or physically practical for many service tasks.',
        ],
        'Siberian Husky' => [
            'group' => 'Working',
            'temperament' => 'Independent, social, energetic',
            'traits' => 'Athletic, expressive, often friendly with strangers',
            'notes' => 'Usually harder to use in service work because independence can compete with handler focus.',
        ],
        'Alaskan Malamute' => [
            'group' => 'Working',
            'temperament' => 'Strong-willed, affectionate, steady',
            'traits' => 'Powerful, social, durable, often less handler-focused than retrievers',
            'notes' => 'Can be dependable in some homes but is not typically an easy service-dog breed.',
        ],
        'German Shorthaired Pointer' => [
            'group' => 'Sporting',
            'temperament' => 'Eager, energetic, intelligent',
            'traits' => 'Trainable, athletic, responsive, high exercise needs',
            'notes' => 'Can work well for active handlers, but exercise needs are significant.',
        ],
        'English Springer Spaniel' => [
            'group' => 'Sporting',
            'temperament' => 'Cheerful, biddable, affectionate',
            'traits' => 'Trainable, social, eager to work, medium size',
            'notes' => 'Often a nice middle ground for alert/response work. Some lines can be sensitive.',
        ],
        'Cocker Spaniel' => [
            'group' => 'Sporting',
            'temperament' => 'Sweet, merry, responsive',
            'traits' => 'Compact, affectionate, trainable, portable',
            'notes' => 'Can fit smaller service work roles well. Grooming and ear care matter.',
        ],
        'Brittany' => [
            'group' => 'Sporting',
            'temperament' => 'Bright, eager, sensitive',
            'traits' => 'Quick learner, athletic, handler-aware',
            'notes' => 'Can succeed with active handlers but may need extra help settling in busy environments.',
        ],
        'Vizsla' => [
            'group' => 'Sporting',
            'temperament' => 'Velcro, sensitive, eager',
            'traits' => 'Highly bonded, athletic, responsive, affectionate',
            'notes' => 'Can be wonderful for close-working teams, though some are too soft for heavy public stress.',
        ],
        'Weimaraner' => [
            'group' => 'Sporting',
            'temperament' => 'Bold, energetic, attached',
            'traits' => 'Athletic, intelligent, people-oriented, strong need for activity',
            'notes' => 'Can task well with the right handler, but energy and arousal levels need management.',
        ],
        'Flat-Coated Retriever' => [
            'group' => 'Sporting',
            'temperament' => 'Upbeat, social, eager',
            'traits' => 'Friendly, trainable, soft-mouthed, fun-loving',
            'notes' => 'Can resemble a lighter Golden/Lab style, though some stay very adolescent for a long time.',
        ],
        'Portuguese Water Dog' => [
            'group' => 'Working',
            'temperament' => 'Bright, active, engaging',
            'traits' => 'Trainable, lower shedding coat, athletic, people-oriented',
            'notes' => 'Can be a good option for some homes, but energy and grooming are real commitments.',
        ],
        'Miniature Poodle' => [
            'group' => 'Non-Sporting',
            'temperament' => 'Smart, lively, attentive',
            'traits' => 'Portable, trainable, often excellent for alerts',
            'notes' => 'Very practical for smaller task sets. Grooming remains high maintenance.',
        ],
        'Toy Poodle' => [
            'group' => 'Toy',
            'temperament' => 'Bright, alert, devoted',
            'traits' => 'Tiny, smart, portable, often highly attuned to handler changes',
            'notes' => 'Best suited for alert/response rather than physical support tasks.',
        ],
        'Bichon Frise' => [
            'group' => 'Non-Sporting',
            'temperament' => 'Cheerful, affectionate, social',
            'traits' => 'Small, adaptable, friendly, lower shedding coat',
            'notes' => 'Can fit psychiatric alert/response roles. Coat maintenance is significant.',
        ],
        'Havanese' => [
            'group' => 'Toy',
            'temperament' => 'Sociable, gentle, bright',
            'traits' => 'Small, portable, people-oriented, often easy to live with',
            'notes' => 'Good candidate for small-dog public access and alert-type tasks.',
        ],
        'Cavalier King Charles Spaniel' => [
            'group' => 'Toy',
            'temperament' => 'Affectionate, soft, friendly',
            'traits' => 'Portable, social, naturally people-focused',
            'notes' => 'Lovely temperament for companionship and emotional grounding. Health screening is especially important in this breed.',
        ],
        'Papillon' => [
            'group' => 'Toy',
            'temperament' => 'Happy, sharp, agile',
            'traits' => 'Very trainable, portable, surprisingly athletic',
            'notes' => 'Excellent for alert tasks and travel-friendly teams. Not for physical support work.',
        ],
        'Yorkshire Terrier' => [
            'group' => 'Toy',
            'temperament' => 'Bold, devoted, alert',
            'traits' => 'Tiny, portable, often highly tuned to routine and handler patterns',
            'notes' => 'Can fit alert-oriented work, though some individuals can be vocal or reactive.',
        ],
        'Shih Tzu' => [
            'group' => 'Toy',
            'temperament' => 'Friendly, calm, affectionate',
            'traits' => 'Portable, low-demand energy, people-friendly',
            'notes' => 'Can be practical for companionship and some response tasks. Heat care and grooming matter.',
        ],
        'Pomeranian' => [
            'group' => 'Toy',
            'temperament' => 'Lively, bold, alert',
            'traits' => 'Tiny, portable, attentive, expressive',
            'notes' => 'Can work for alert tasks if barking and arousal are well managed.',
        ],
        'Chihuahua' => [
            'group' => 'Toy',
            'temperament' => 'Loyal, alert, sensitive',
            'traits' => 'Very portable, strongly bonded, quick to notice routine changes',
            'notes' => 'Can suit alert/response work, but confidence and neutrality in public are key.',
        ],
        'Beagle' => [
            'group' => 'Hound',
            'temperament' => 'Cheerful, curious, social',
            'traits' => 'Food motivated, friendly, compact, scent-driven',
            'notes' => 'Can be good-natured partners, though scent distraction and vocalizing may complicate service work.',
        ],
        'Basset Hound' => [
            'group' => 'Hound',
            'temperament' => 'Easygoing, affectionate, patient',
            'traits' => 'Calm, social, strong nose, slower pace',
            'notes' => 'Can be emotionally steady but often less precise and less eager to work than top service breeds.',
        ],
        'Whippet' => [
            'group' => 'Hound',
            'temperament' => 'Gentle, quiet, sensitive',
            'traits' => 'Light build, calm indoors, affectionate, typically low odor',
            'notes' => 'Can be lovely for low-key teams, though environmental sensitivity varies.',
        ],
        'Greyhound' => [
            'group' => 'Hound',
            'temperament' => 'Quiet, gentle, reserved',
            'traits' => 'Calm in the house, elegant, low-maintenance coat',
            'notes' => 'Some do very well in public, but sensitivity and startle recovery should be assessed individually.',
        ],
        'Rhodesian Ridgeback' => [
            'group' => 'Hound',
            'temperament' => 'Independent, loyal, composed',
            'traits' => 'Athletic, reserved with strangers, strong-willed',
            'notes' => 'Can be stable but usually requires more handler skill than classic service breeds.',
        ],
        'Dalmatian' => [
            'group' => 'Non-Sporting',
            'temperament' => 'Bright, active, outgoing',
            'traits' => 'Athletic, smart, eye-catching, high stamina',
            'notes' => 'Can be versatile but needs enough physical outlet and steady public neutrality work.',
        ],
        'Mixed Breed' => [
            'group' => 'Mixed',
            'temperament' => 'Varies by individual dog',
            'traits' => 'Can combine strengths from multiple breeds; actual working qualities depend on the dog in front of you',
            'notes' => 'For service work, individual temperament, health, trainability, and recovery matter more than label alone.',
        ],
        'Unknown / Rescue Mix' => [
            'group' => 'Mixed',
            'temperament' => 'Unknown until observed over time',
            'traits' => 'Traits can emerge gradually as the dog settles and matures',
            'notes' => 'Use the notes field to record what you actually see: confidence, recovery, neutrality, sociability, sound sensitivity, and drive.',
        ],
        'Other / Custom' => [
            'group' => 'Custom',
            'temperament' => 'Enter your own description',
            'traits' => 'Use when the dog is a rare breed, cross, or specific working line not listed here',
            'notes' => 'You can still type any breed name manually. The dropdown is there to help, not restrict.',
        ],
    ];

    $details = [
        'Labrador Retriever' => ['size' => 'Large', 'weight_range' => '55-80 lbs', 'coat_type' => 'Short double coat', 'shedding' => 'High', 'exercise_level' => 'High'],
        'Golden Retriever' => ['size' => 'Large', 'weight_range' => '55-75 lbs', 'coat_type' => 'Medium-long double coat', 'shedding' => 'High', 'exercise_level' => 'High'],
        'German Shepherd Dog' => ['size' => 'Large', 'weight_range' => '50-90 lbs', 'coat_type' => 'Medium double coat', 'shedding' => 'High', 'exercise_level' => 'High'],
        'Standard Poodle' => ['size' => 'Large', 'weight_range' => '40-70 lbs', 'coat_type' => 'Curly single coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate to high'],
        'Bernese Mountain Dog' => ['size' => 'Giant', 'weight_range' => '70-115 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'High', 'exercise_level' => 'Moderate'],
        'Great Dane' => ['size' => 'Giant', 'weight_range' => '100-175 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'Moderate'],
        'Boxer' => ['size' => 'Large', 'weight_range' => '50-80 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'High'],
        'Doberman Pinscher' => ['size' => 'Large', 'weight_range' => '60-100 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'High'],
        'Rottweiler' => ['size' => 'Large', 'weight_range' => '80-135 lbs', 'coat_type' => 'Short double coat', 'shedding' => 'Moderate', 'exercise_level' => 'Moderate to high'],
        'Newfoundland' => ['size' => 'Giant', 'weight_range' => '100-150 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'High', 'exercise_level' => 'Moderate'],
        'Border Collie' => ['size' => 'Medium', 'weight_range' => '30-55 lbs', 'coat_type' => 'Smooth or rough double coat', 'shedding' => 'Moderate', 'exercise_level' => 'Very high'],
        'Australian Shepherd' => ['size' => 'Medium', 'weight_range' => '40-65 lbs', 'coat_type' => 'Medium double coat', 'shedding' => 'Moderate to high', 'exercise_level' => 'Very high'],
        'Collie' => ['size' => 'Large', 'weight_range' => '50-75 lbs', 'coat_type' => 'Rough or smooth coat', 'shedding' => 'Moderate to high', 'exercise_level' => 'Moderate'],
        'Shetland Sheepdog' => ['size' => 'Small to medium', 'weight_range' => '15-25 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'High', 'exercise_level' => 'Moderate to high'],
        'Corgi' => ['size' => 'Small to medium', 'weight_range' => '22-38 lbs', 'coat_type' => 'Medium double coat', 'shedding' => 'High', 'exercise_level' => 'Moderate'],
        'Belgian Malinois' => ['size' => 'Large', 'weight_range' => '40-80 lbs', 'coat_type' => 'Short double coat', 'shedding' => 'Moderate', 'exercise_level' => 'Very high'],
        'Great Pyrenees' => ['size' => 'Giant', 'weight_range' => '85-160 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'High', 'exercise_level' => 'Moderate'],
        'Saint Bernard' => ['size' => 'Giant', 'weight_range' => '120-180 lbs', 'coat_type' => 'Short or long coat', 'shedding' => 'Moderate to high', 'exercise_level' => 'Low to moderate'],
        'Mastiff' => ['size' => 'Giant', 'weight_range' => '120-230 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low to moderate', 'exercise_level' => 'Low to moderate'],
        'Siberian Husky' => ['size' => 'Medium', 'weight_range' => '35-60 lbs', 'coat_type' => 'Medium double coat', 'shedding' => 'High', 'exercise_level' => 'Very high'],
        'Alaskan Malamute' => ['size' => 'Large', 'weight_range' => '70-100 lbs', 'coat_type' => 'Thick double coat', 'shedding' => 'High', 'exercise_level' => 'High'],
        'German Shorthaired Pointer' => ['size' => 'Large', 'weight_range' => '45-70 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'Very high'],
        'English Springer Spaniel' => ['size' => 'Medium', 'weight_range' => '40-55 lbs', 'coat_type' => 'Medium coat with feathering', 'shedding' => 'Moderate', 'exercise_level' => 'High'],
        'Cocker Spaniel' => ['size' => 'Medium', 'weight_range' => '20-30 lbs', 'coat_type' => 'Medium-silky coat', 'shedding' => 'Moderate', 'exercise_level' => 'Moderate'],
        'Brittany' => ['size' => 'Medium', 'weight_range' => '30-40 lbs', 'coat_type' => 'Dense coat with feathering', 'shedding' => 'Moderate', 'exercise_level' => 'Very high'],
        'Vizsla' => ['size' => 'Large', 'weight_range' => '45-65 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low to moderate', 'exercise_level' => 'Very high'],
        'Weimaraner' => ['size' => 'Large', 'weight_range' => '55-90 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low to moderate', 'exercise_level' => 'Very high'],
        'Flat-Coated Retriever' => ['size' => 'Large', 'weight_range' => '55-75 lbs', 'coat_type' => 'Medium-long flat coat', 'shedding' => 'Moderate', 'exercise_level' => 'High'],
        'Portuguese Water Dog' => ['size' => 'Medium', 'weight_range' => '35-60 lbs', 'coat_type' => 'Curly or wavy single coat', 'shedding' => 'Low', 'exercise_level' => 'High'],
        'Miniature Poodle' => ['size' => 'Small', 'weight_range' => '10-20 lbs', 'coat_type' => 'Curly single coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Toy Poodle' => ['size' => 'Toy', 'weight_range' => '4-10 lbs', 'coat_type' => 'Curly single coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Bichon Frise' => ['size' => 'Small', 'weight_range' => '12-18 lbs', 'coat_type' => 'Curly double coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Havanese' => ['size' => 'Small', 'weight_range' => '7-13 lbs', 'coat_type' => 'Long silky coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Cavalier King Charles Spaniel' => ['size' => 'Small', 'weight_range' => '13-18 lbs', 'coat_type' => 'Silky medium coat', 'shedding' => 'Moderate', 'exercise_level' => 'Low to moderate'],
        'Papillon' => ['size' => 'Toy', 'weight_range' => '5-10 lbs', 'coat_type' => 'Silky coat with fringe', 'shedding' => 'Low to moderate', 'exercise_level' => 'Moderate'],
        'Yorkshire Terrier' => ['size' => 'Toy', 'weight_range' => '4-7 lbs', 'coat_type' => 'Long silky single coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Shih Tzu' => ['size' => 'Toy', 'weight_range' => '9-16 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'Low', 'exercise_level' => 'Low to moderate'],
        'Pomeranian' => ['size' => 'Toy', 'weight_range' => '3-7 lbs', 'coat_type' => 'Long double coat', 'shedding' => 'Moderate to high', 'exercise_level' => 'Moderate'],
        'Chihuahua' => ['size' => 'Toy', 'weight_range' => '3-6 lbs', 'coat_type' => 'Smooth or long coat', 'shedding' => 'Low to moderate', 'exercise_level' => 'Low to moderate'],
        'Beagle' => ['size' => 'Medium', 'weight_range' => '20-30 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'Moderate to high'],
        'Basset Hound' => ['size' => 'Medium', 'weight_range' => '40-65 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Moderate', 'exercise_level' => 'Low to moderate'],
        'Whippet' => ['size' => 'Medium', 'weight_range' => '25-40 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Greyhound' => ['size' => 'Large', 'weight_range' => '60-80 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low', 'exercise_level' => 'Moderate'],
        'Rhodesian Ridgeback' => ['size' => 'Large', 'weight_range' => '65-90 lbs', 'coat_type' => 'Short coat', 'shedding' => 'Low to moderate', 'exercise_level' => 'Moderate to high'],
        'Dalmatian' => ['size' => 'Large', 'weight_range' => '45-70 lbs', 'coat_type' => 'Short coat', 'shedding' => 'High', 'exercise_level' => 'High'],
        'Mixed Breed' => ['size' => 'Varies', 'weight_range' => 'Varies', 'coat_type' => 'Varies', 'shedding' => 'Varies', 'exercise_level' => 'Varies'],
        'Unknown / Rescue Mix' => ['size' => 'Unknown', 'weight_range' => 'Unknown', 'coat_type' => 'Unknown', 'shedding' => 'Unknown', 'exercise_level' => 'Unknown until observed'],
        'Other / Custom' => ['size' => 'Custom', 'weight_range' => 'Custom', 'coat_type' => 'Custom', 'shedding' => 'Custom', 'exercise_level' => 'Custom'],
    ];


    // GUIDEPAW_BREED_EXPANSION_V1
    // Supplemental breed catalog. This preserves the detailed entries above and only fills in missing breeds.
    // Breed is still saved as free text, so users can type custom mixes, rare breeds, or unknown lineage.
    $supplementalBreedGroups = [
        'Sporting' => [
            "American Water Spaniel", "Barbet", "Boykin Spaniel", "Bracco Italiano", "Brittany",
            "Chesapeake Bay Retriever", "Clumber Spaniel", "Cocker Spaniel", "Curly-Coated Retriever",
            "English Cocker Spaniel", "English Setter", "English Springer Spaniel", "Field Spaniel",
            "Flat-Coated Retriever", "German Shorthaired Pointer", "German Wirehaired Pointer",
            "Golden Retriever", "Gordon Setter", "Irish Red and White Setter", "Irish Setter",
            "Irish Water Spaniel", "Labrador Retriever", "Lagotto Romagnolo",
            "Nederlandse Kooikerhondje", "Nova Scotia Duck Tolling Retriever", "Pointer",
            "Portuguese Water Dog", "Spinone Italiano", "Sussex Spaniel", "Vizsla", "Weimaraner",
            "Welsh Springer Spaniel", "Wirehaired Pointing Griffon", "Wirehaired Vizsla"
        ],
        'Hound' => [
            "Afghan Hound", "American English Coonhound", "American Foxhound", "Azawakh",
            "Basenji", "Basset Fauve de Bretagne", "Basset Hound", "Beagle",
            "Black and Tan Coonhound", "Bloodhound", "Bluetick Coonhound", "Borzoi",
            "Cirneco dell'Etna", "Dachshund", "English Foxhound", "Grand Basset Griffon Vendeen",
            "Greyhound", "Hamiltonstovare", "Harrier", "Ibizan Hound", "Irish Wolfhound",
            "Norwegian Elkhound", "Otterhound", "Petit Basset Griffon Vendeen", "Pharaoh Hound",
            "Plott Hound", "Portuguese Podengo Pequeno", "Redbone Coonhound", "Rhodesian Ridgeback",
            "Saluki", "Scottish Deerhound", "Sloughi", "Treeing Walker Coonhound", "Whippet"
        ],
        'Working' => [
            "Akita", "Alaskan Malamute", "Anatolian Shepherd Dog", "Bernese Mountain Dog",
            "Black Russian Terrier", "Boerboel", "Boxer", "Bullmastiff", "Cane Corso",
            "Chinook", "Doberman Pinscher", "Dogo Argentino", "Dogue de Bordeaux",
            "German Pinscher", "Giant Schnauzer", "Great Dane", "Great Pyrenees",
            "Greater Swiss Mountain Dog", "Komondor", "Kuvasz", "Leonberger", "Mastiff",
            "Neapolitan Mastiff", "Newfoundland", "Rottweiler", "Saint Bernard", "Samoyed",
            "Siberian Husky", "Standard Schnauzer", "Tibetan Mastiff"
        ],
        'Terrier' => [
            "Airedale Terrier", "American Hairless Terrier", "American Staffordshire Terrier",
            "Australian Terrier", "Bedlington Terrier", "Border Terrier", "Bull Terrier",
            "Cairn Terrier", "Cesky Terrier", "Dandie Dinmont Terrier", "Glen of Imaal Terrier",
            "Irish Terrier", "Kerry Blue Terrier", "Lakeland Terrier", "Manchester Terrier",
            "Miniature Bull Terrier", "Miniature Schnauzer", "Norfolk Terrier", "Norwich Terrier",
            "Parson Russell Terrier", "Rat Terrier", "Russell Terrier", "Scottish Terrier",
            "Sealyham Terrier", "Skye Terrier", "Smooth Fox Terrier", "Soft Coated Wheaten Terrier",
            "Staffordshire Bull Terrier", "Teddy Roosevelt Terrier", "Welsh Terrier",
            "West Highland White Terrier", "Wire Fox Terrier"
        ],
        'Toy' => [
            "Affenpinscher", "Biewer Terrier", "Brussels Griffon", "Cavalier King Charles Spaniel",
            "Chihuahua", "Chinese Crested", "English Toy Spaniel", "Havanese", "Italian Greyhound",
            "Japanese Chin", "Maltese", "Manchester Terrier (Toy)", "Miniature Pinscher",
            "Papillon", "Pekingese", "Pomeranian", "Poodle (Toy)", "Pug", "Russian Toy",
            "Russian Tsvetnaya Bolonka", "Shih Tzu", "Silky Terrier", "Toy Fox Terrier",
            "Yorkshire Terrier"
        ],
        'Non-Sporting' => [
            "American Eskimo Dog", "Bichon Frise", "Boston Terrier", "Bulldog",
            "Chinese Shar-Pei", "Chow Chow", "Coton de Tulear", "Dalmatian", "Finnish Spitz",
            "French Bulldog", "Keeshond", "Lhasa Apso", "Löwchen", "Norwegian Lundehund",
            "Poodle", "Poodle (Miniature)", "Poodle (Standard)", "Schipperke", "Shiba Inu",
            "Tibetan Spaniel", "Tibetan Terrier", "Xoloitzcuintli"
        ],
        'Herding' => [
            "Australian Cattle Dog", "Australian Shepherd", "Bearded Collie", "Beauceron",
            "Belgian Laekenois", "Belgian Malinois", "Belgian Sheepdog", "Belgian Tervuren",
            "Bergamasco Sheepdog", "Berger Picard", "Border Collie", "Bouvier des Flandres",
            "Briard", "Canaan Dog", "Cardigan Welsh Corgi", "Collie", "Entlebucher Mountain Dog",
            "Finnish Lapphund", "German Shepherd Dog", "Icelandic Sheepdog", "Lancashire Heeler",
            "Miniature American Shepherd", "Mudi", "Norwegian Buhund", "Old English Sheepdog",
            "Pembroke Welsh Corgi", "Polish Lowland Sheepdog", "Puli", "Pumi", "Pyrenean Shepherd",
            "Shetland Sheepdog", "Spanish Water Dog", "Swedish Vallhund"
        ],
        'Rare / International / Foundation Stock' => [
            "Aidi", "Appenzeller Sennenhund", "Australian Kelpie", "Australian Stumpy Tail Cattle Dog",
            "Austrian Pinscher", "Barbado da Terceira", "Bavarian Mountain Scent Hound",
            "Bolognese", "Bohemian Shepherd", "Braque du Bourbonnais", "Braque Francais Pyrenean",
            "Broholmer", "Carolina Dog", "Catahoula Leopard Dog", "Caucasian Shepherd Dog",
            "Central Asian Shepherd Dog", "Czechoslovakian Vlcak", "Danish-Swedish Farmdog",
            "Dutch Shepherd", "Estrela Mountain Dog", "Eurasier", "German Longhaired Pointer",
            "German Spitz", "Hokkaido", "Jagdterrier", "Jindo", "Kai Ken", "Karelian Bear Dog",
            "Kishu Ken", "Kromfohrlander", "Lapponian Herder", "Mountain Cur", "Norrbottenspets",
            "Perro de Presa Canario", "Peruvian Inca Orchid", "Portuguese Podengo",
            "Portuguese Podengo Pequeno", "Pyrenean Mastiff", "Rafeiro do Alentejo",
            "Romanian Mioritic Shepherd Dog", "Schapendoes", "Shikoku", "Slovakian Wirehaired Pointer",
            "Stabyhoun", "Swedish Lapphund", "Taiwan Dog", "Thai Ridgeback", "Tornjak",
            "Tosa", "Transylvanian Hound", "Yakutian Laika"
        ],
        'Designer / Hybrid' => [
            "Aussiedoodle", "Aussalier", "Beaglier", "Bernedoodle", "Bichpoo", "Bordoodle",
            "Boxador", "Cavachon", "Cavador", "Cavapoo", "Chiweenie", "Chorkie", "Cockalier",
            "Cockapoo", "Corgipoo", "Danoodle", "Doxiepoo", "Double Doodle", "Frenchton",
            "Gerberian Shepsky", "Goldador", "Golden Mountain Doodle", "Golden Shepherd",
            "Goldendoodle", "Havapoo", "Huskydoodle", "Irish Doodle", "Jackapoo", "Labradoodle",
            "Labrastaff", "Maltipoo", "Miniature Goldendoodle", "Miniature Labradoodle",
            "Morkie", "Newfypoo", "Peekapoo", "Pitsky", "Pomapoo", "Pomchi", "Pomsky",
            "Poochon", "Puggle", "Rottle", "Saint Berdoodle", "Schnoodle", "Sheepadoodle",
            "Shih-Poo", "Shorkie", "Springerdoodle", "Vizsladoodle", "Weimardoodle",
            "Whoodle", "Yorkipoo"
        ],
        'Mixed / Unknown / Other' => [
            "Mixed Breed", "Mixed Breed - Large", "Mixed Breed - Medium", "Mixed Breed - Small",
            "Unknown Breed", "Other / Not Listed"
        ],
    ];

    $groupBreedDefaults = [
        'Sporting' => [
            'temperament' => 'Generally active, social, responsive, and people-oriented',
            'traits' => 'Often trainable and handler-focused; many sporting breeds are strong candidates for service-dog work when temperament is stable',
            'notes' => 'Consider energy level, settling skills, grooming needs, and public neutrality for the individual dog.',
        ],
        'Hound' => [
            'temperament' => 'Often independent, scent- or sight-driven, and gentle with familiar people',
            'traits' => 'Can be steady companions; distraction management and recall/focus may need extra work',
            'notes' => 'Evaluate the individual dog carefully, especially around scent, prey drive, vocalizing, and environmental focus.',
        ],
        'Working' => [
            'temperament' => 'Often powerful, confident, loyal, and task-oriented',
            'traits' => 'May be capable and steady, but size, public perception, guarding tendencies, and orthopedic health matter',
            'notes' => 'Best candidates need excellent neutrality, social stability, and clear handler focus.',
        ],
        'Terrier' => [
            'temperament' => 'Often bold, alert, clever, and energetic',
            'traits' => 'Can learn quickly; prey drive, persistence, and vocal behavior may need careful management',
            'notes' => 'Often better suited to alert/response or smaller task sets than heavy physical support work.',
        ],
        'Toy' => [
            'temperament' => 'Often portable, attentive, companion-oriented, and alert',
            'traits' => 'Can be useful for alert/response work; size limits physical support tasks',
            'notes' => 'Confidence, neutrality, and safe handling in public are especially important.',
        ],
        'Non-Sporting' => [
            'temperament' => 'Varies widely by breed and line',
            'traits' => 'Companion qualities, trainability, coat care, energy, and heat tolerance vary significantly',
            'notes' => 'Use this as a starting point and evaluate the individual dog, health, structure, and working temperament.',
        ],
        'Herding' => [
            'temperament' => 'Often intelligent, responsive, observant, and handler-aware',
            'traits' => 'Highly trainable; may be sensitive, motion-aware, vocal, or prone to environmental scanning',
            'notes' => 'Strong candidates need excellent off-switch, public neutrality, and stable nerves.',
        ],
        'Rare / International / Foundation Stock' => [
            'temperament' => 'Breed traits vary widely; research the specific breed, line, and purpose',
            'traits' => 'Added for broader breed selection and record accuracy',
            'notes' => 'For rare breeds, rely on individual assessment, breeder/rescue history, health screening, and observed public-access temperament.',
        ],
        'Designer / Hybrid' => [
            'temperament' => 'Mixed traits from parent breeds; consistency varies by breeder, generation, health testing, early socialization, and the individual dog',
            'traits' => 'May combine useful qualities, but coat, size, drive, nerves, confidence, public neutrality, and trainability can be less predictable than established breeds',
            'notes' => 'Service-work suitability must be based on the individual dog, not the designer-breed label. Review parent breeds, generation, structure, health history, recovery from stress, task aptitude, and ability to stay neutral in public.',
        ],
        'Mixed / Unknown / Other' => [
            'temperament' => 'Individual temperament, health, structure, and training history matter more than the label',
            'traits' => 'Use when breed is mixed, unknown, rare, custom, or not listed; suitability should be evaluated through observed behavior and task needs',
            'notes' => 'For custom or unknown breeds, assess the dog directly: confidence, public neutrality, handler focus, recovery from stress, physical soundness, task aptitude, grooming/coat needs, and whether the dog can work calmly without distress.',
        ],
    ];

    foreach ($supplementalBreedGroups as $groupName => $breedNames) {
        foreach ($breedNames as $breedName) {
            if (!isset($catalog[$breedName])) {
                $catalog[$breedName] = array_merge(
                    ['group' => $groupName],
                    $groupBreedDefaults[$groupName] ?? $groupBreedDefaults['Mixed / Unknown / Other']
                );
            }
        }
    }


    // GUIDEPAW_DESIGNER_SERVICE_WORK_DETAILS_V1
    // Designer/crossbreed guidance is intentionally practical, not a guarantee.
    // Published assistance-dog program data exists for some crosses, especially Labrador/Golden crosses,
    // while many popular designer breeds require parent-breed and individual-dog assessment.
    $designerServiceWorkDetails = [
        'Labrador Retriever / Golden Retriever Cross' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often friendly, steady, people-oriented, food motivated, and eager to work',
            'traits' => 'Purpose-bred Lab/Golden crosses are widely used in assistance and guide dog programs because they often combine trainability, sociability, biddability, and practical working size',
            'size' => 'Large',
            'weight' => '55-80 lb typical, but individual size varies',
            'coat' => 'Short to medium double coat',
            'shedding' => 'Moderate to heavy',
            'exercise' => 'Moderate to high; usually needs daily exercise plus settling skills',
            'notes' => 'Common service-work fit: guide work, retrieval, item delivery, hearing/response tasks, PTSD interruption, public access, and many handler-focused tasks. Watch points: adolescence energy, shedding, food scavenging, orthopedic health, and whether the individual dog can remain neutral in public.',
        ],
        'Goldador' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Usually social, eager, handler-focused, and trainable when temperament is stable',
            'traits' => 'Labrador/Golden cross; often combines the Labrador’s work drive and food motivation with the Golden’s softness and people focus',
            'size' => 'Large',
            'weight' => '55-80 lb typical',
            'coat' => 'Short to medium double coat',
            'shedding' => 'Moderate to heavy',
            'exercise' => 'Moderate to high',
            'notes' => 'Common service-work fit: broad service-dog roles, retrieval, public access, guide-style foundation work, psychiatric response, medical response, and handler-focused tasking. Watch points: joint health, weight management, mouthiness, shedding, and early neutrality training.',
        ],
        'Labradoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often intelligent, social, energetic, and people-oriented; consistency varies by generation and parent lines',
            'traits' => 'Can combine Labrador trainability with Poodle intelligence and coat traits; coat, shedding, size, and temperament can vary widely',
            'size' => 'Medium to large',
            'weight' => '30-80 lb depending on Poodle size and generation',
            'coat' => 'Wavy to curly; grooming needs are usually significant',
            'shedding' => 'Low to moderate, but not guaranteed hypoallergenic',
            'exercise' => 'Moderate to high; needs mental work and an off-switch',
            'notes' => 'Common service-work fit: allergy-conscious households, psychiatric response, alerts, retrieval, public access, and handler-focused tasks. Watch points: grooming burden, coat matting, adolescent energy, sound/environment sensitivity, and variability between F1/F1B/multigenerational lines.',
        ],
        'Goldendoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often affectionate, social, intelligent, and handler-aware; predictability depends heavily on breeding quality and generation',
            'traits' => 'May combine Golden Retriever sociability with Poodle intelligence and coat traits; individual temperament should be tested carefully',
            'size' => 'Small to large depending on parent size',
            'weight' => '20-90 lb depending on miniature/medium/standard lines',
            'coat' => 'Wavy to curly; regular professional grooming often needed',
            'shedding' => 'Low to moderate, but not guaranteed',
            'exercise' => 'Moderate to high',
            'notes' => 'Common service-work fit: psychiatric response, medical alerts, retrieval, handler interruption tasks, and public access for stable candidates. Watch points: grooming cost, coat maintenance, overexcitement, sensitivity, inconsistent size, and the need for strong public-neutrality training.',
        ],
        'Miniature Goldendoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often social, bright, affectionate, and alert; stability varies by parent lines',
            'traits' => 'Smaller size can suit alert/response roles, but limits physical task work',
            'size' => 'Small to medium',
            'weight' => '15-45 lb typical, varies widely',
            'coat' => 'Wavy to curly; grooming intensive',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate',
            'notes' => 'Common service-work fit: medical alerts, psychiatric response, interruption tasks, travel-friendly public access, and routine-based alerts. Not appropriate for mobility support or bracing. Watch points: confidence in crowds, handling tolerance, grooming, and barking/arousal.',
        ],
        'Bernedoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often affectionate, people-oriented, and steady, but can vary from goofy and social to sensitive or stubborn',
            'traits' => 'May combine Bernese size/steadiness with Poodle intelligence and coat traits; health and structure matter greatly',
            'size' => 'Medium to giant depending on parent size',
            'weight' => '40-100+ lb depending on line',
            'coat' => 'Wavy to curly; high grooming needs',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate; needs conditioning without overloading joints',
            'notes' => 'Common service-work fit: psychiatric response, grounding, retrieval, item carry, and calm public access for stable candidates. Watch points: orthopedic health, heat tolerance, size management, grooming burden, adolescence, and whether the dog can work without leaning into protective behavior.',
        ],
        'Aussiedoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often very intelligent, handler-aware, energetic, and sensitive',
            'traits' => 'May combine Australian Shepherd responsiveness with Poodle intelligence; can be brilliant but may be too busy or environmentally aware for some handlers',
            'size' => 'Small to large depending on parent size',
            'weight' => '20-70 lb typical range',
            'coat' => 'Wavy to curly; regular grooming needed',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'High mental and physical needs',
            'notes' => 'Common service-work fit: alerts, response tasks, complex trained behaviors, and active-handler work. Watch points: motion sensitivity, herding behavior, vocalizing, reactivity risk, over-arousal, and the need for a strong off-switch before public access.',
        ],
        'Sheepadoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often social, playful, intelligent, and handler-aware; some may be sensitive or exuberant',
            'traits' => 'May combine Old English Sheepdog sociability with Poodle trainability and coat traits',
            'size' => 'Medium to large',
            'weight' => '45-90 lb typical range',
            'coat' => 'Dense wavy to curly coat; high grooming needs',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate to high',
            'notes' => 'Common service-work fit: psychiatric response, retrieval, grounding, public access for calm candidates, and task work needing size without giant-breed mass. Watch points: grooming, heat, excitability, body awareness in tight spaces, and adolescent impulse control.',
        ],
        'Golden Shepherd' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often intelligent, loyal, trainable, and handler-aware; may inherit guarding or environmental scanning',
            'traits' => 'May combine Golden Retriever sociability with German Shepherd intelligence and work ethic',
            'size' => 'Large',
            'weight' => '50-90 lb typical range',
            'coat' => 'Medium to long double coat',
            'shedding' => 'Moderate to heavy',
            'exercise' => 'Moderate to high; needs structured work and calm public behavior',
            'notes' => 'Common service-work fit: guide-style work in purpose-bred programs, psychiatric response, retrieval, and complex task training. Watch points: protectiveness, suspicion, sensitivity, reactivity, public perception, and the need for exceptional neutrality.',
        ],
        'Cockapoo' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often cheerful, affectionate, alert, and people-oriented',
            'traits' => 'May combine Cocker Spaniel sociability with Poodle trainability; size and coat vary',
            'size' => 'Small to medium',
            'weight' => '12-30 lb typical range',
            'coat' => 'Wavy to curly; regular grooming required',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate',
            'notes' => 'Common service-work fit: hearing alerts, medical alerts, psychiatric response, interruption tasks, and smaller public-access teams. Watch points: ear care, grooming, barking, separation distress, and confidence in busy public settings.',
        ],
        'Cavapoo' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often affectionate, gentle, social, and handler-focused',
            'traits' => 'May combine Cavalier softness with Poodle intelligence; usually better for alert/response than physical tasks',
            'size' => 'Small',
            'weight' => '10-25 lb typical range',
            'coat' => 'Wavy to curly; regular grooming needed',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Low to moderate',
            'notes' => 'Common service-work fit: psychiatric response, medical alerts, interruption tasks, and travel-friendly support for handlers who need a smaller dog. Watch points: heart/eye/knee health, confidence, barking, grooming, and limits for physical task work.',
        ],
        'Maltipoo' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often affectionate, alert, people-focused, and portable',
            'traits' => 'May suit close handler monitoring and alert-style tasks; physical task capacity is limited by size',
            'size' => 'Toy to small',
            'weight' => '5-20 lb typical range',
            'coat' => 'Soft wavy to curly coat; frequent grooming needed',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Low to moderate',
            'notes' => 'Common service-work fit: medical alerts, psychiatric response, grounding routines, and small-dog travel/public access. Watch points: fragility, dental care, barking, confidence, grooming, and safe handling around crowds or larger dogs.',
        ],
        'Schnoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often clever, alert, loyal, and energetic',
            'traits' => 'May combine Schnauzer alertness with Poodle trainability; size varies from small to large',
            'size' => 'Small to large depending on parent size',
            'weight' => '10-75 lb depending on line',
            'coat' => 'Wiry, wavy, or curly; grooming needs vary',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate',
            'notes' => 'Common service-work fit: alerts, response tasks, routine interruption, and handler-focused work. Watch points: barking, terrier-like intensity, suspicion of strangers, grooming, and the need for public neutrality.',
        ],
        'Whoodle' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Often friendly, playful, clever, and energetic',
            'traits' => 'May combine Soft Coated Wheaten Terrier sociability with Poodle trainability',
            'size' => 'Medium',
            'weight' => '20-45 lb typical range',
            'coat' => 'Wavy to curly; regular grooming needed',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Moderate to high',
            'notes' => 'Common service-work fit: alert/response, psychiatric interruption, and active-handler public access. Watch points: excitability, jumping, prey interest, grooming, and whether the dog can settle calmly in public.',
        ],
        'Poodle Cross / Doodle Mix' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Highly variable; depends on both parent breeds, generation, breeder selection, and individual temperament',
            'traits' => 'Can be intelligent and trainable, but coat, shedding, size, sensitivity, drive, and public-access temperament are not guaranteed',
            'size' => 'Varies by parent breeds',
            'weight' => 'Varies widely',
            'coat' => 'Often wavy or curly; grooming needs are commonly high',
            'shedding' => 'Low to moderate, not guaranteed',
            'exercise' => 'Varies; many need more mental work than expected',
            'notes' => 'Use this for a custom doodle or poodle mix not listed. Service-work fit must be judged by the individual dog: nerves, confidence, recovery from stress, neutrality, handler focus, task aptitude, health, structure, and grooming practicality.',
        ],
        'Custom Designer Breed / Hybrid' => [
            'group' => 'Designer / Hybrid',
            'temperament' => 'Not predictable from the label alone; evaluate parent breeds and the individual dog',
            'traits' => 'Best used when the exact designer mix is known but not listed; record parent breeds in notes when possible',
            'size' => 'Depends on parent breeds',
            'weight' => 'Depends on parent breeds and individual growth',
            'coat' => 'Depends on parent breeds; grooming may be more demanding than expected',
            'shedding' => 'Variable',
            'exercise' => 'Variable',
            'notes' => 'For custom designer breeds, do not rely on the marketing name. Assess service suitability through health, structure, temperament, public neutrality, stress recovery, task aptitude, grooming needs, and whether the dog can work calmly without distress.',
        ],
    ];

    foreach ($designerServiceWorkDetails as $breedName => $breedInfo) {
        $catalog[$breedName] = array_merge($catalog[$breedName] ?? [], $breedInfo);
    }

    foreach ($catalog as $breedName => &$breedInfo) {
        $breedInfo = array_merge([
            'size' => 'Varies',
            'weight_range' => 'Varies',
            'coat_type' => 'Varies',
            'shedding' => 'Varies',
            'exercise_level' => 'Varies',
        ], $breedInfo, $details[$breedName] ?? []);
    }
    unset($breedInfo);

    return $catalog;
}
