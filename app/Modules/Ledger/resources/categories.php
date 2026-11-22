<?php

declare(strict_types=1);

return [
    [
        'code' => 'food',
        'type' => 'expense',
        'emoji' => '🛒',
        'children' => [
            [
                'code' => 'food.groceries',
                'emoji' => '🏪',
                'children' => [
                    ['code' => 'food.groceries.supermarket', 'emoji' => '🥫'],
                    ['code' => 'food.groceries.meat_fish', 'emoji' => '🥩'],
                    ['code' => 'food.groceries.fruit_vegetables', 'emoji' => '🥕'],
                    ['code' => 'food.groceries.bakery', 'emoji' => '🥖'],
                    ['code' => 'food.groceries.dairy', 'emoji' => '🧀'],
                    ['code' => 'food.groceries.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'food.eating_out',
                'emoji' => '🍽️',
                'children' => [
                    ['code' => 'food.eating_out.cafes', 'emoji' => '☕'],
                    ['code' => 'food.eating_out.restaurants', 'emoji' => '🍝'],
                    ['code' => 'food.eating_out.takeaway', 'emoji' => '🥡'],
                    ['code' => 'food.eating_out.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'home',
        'type' => 'expense',
        'emoji' => '🏠',
        'children' => [
            [
                'code' => 'home.housing',
                'emoji' => '🔑',
                'children' => [
                    ['code' => 'home.housing.rent', 'emoji' => '🏠'],
                    ['code' => 'home.housing.mortgage', 'emoji' => '🏦'],
                    ['code' => 'home.housing.building_charges', 'emoji' => '🏢'],
                    ['code' => 'home.housing.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'home.utilities',
                'emoji' => '💡',
                'children' => [
                    ['code' => 'home.utilities.electricity', 'emoji' => '⚡'],
                    ['code' => 'home.utilities.water', 'emoji' => '💧'],
                    ['code' => 'home.utilities.gas_heating', 'emoji' => '🔥'],
                    ['code' => 'home.utilities.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'home.telecom',
                'emoji' => '📡',
                'children' => [
                    ['code' => 'home.telecom.internet', 'emoji' => '🌐'],
                    ['code' => 'home.telecom.mobile_phone', 'emoji' => '📱'],
                    ['code' => 'home.telecom.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'family',
        'type' => 'expense',
        'emoji' => '👨‍👩‍👧',
        'children' => [
            [
                'code' => 'family.children',
                'emoji' => '👶',
                'children' => [
                    ['code' => 'family.children.baby_supplies', 'emoji' => '🍼'],
                    ['code' => 'family.children.toys', 'emoji' => '🧸'],
                    ['code' => 'family.children.childcare', 'emoji' => '🧑‍🍼'],
                    ['code' => 'family.children.pocket_money', 'emoji' => '💵'],
                    ['code' => 'family.children.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'family.education',
                'emoji' => '🎓',
                'children' => [
                    ['code' => 'family.education.school_fees', 'emoji' => '🏫'],
                    ['code' => 'family.education.supplies_books', 'emoji' => '✏️'],
                    ['code' => 'family.education.tutoring', 'emoji' => '📚'],
                    ['code' => 'family.education.university', 'emoji' => '🎓'],
                    ['code' => 'family.education.activities', 'emoji' => '🎨'],
                    ['code' => 'family.education.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'family.pets',
                'emoji' => '🐾',
                'children' => [
                    ['code' => 'family.pets.pet_food', 'emoji' => '🦴'],
                    ['code' => 'family.pets.vet', 'emoji' => '🐕'],
                    ['code' => 'family.pets.accessories', 'emoji' => '🧶'],
                    ['code' => 'family.pets.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'family.household',
                'emoji' => '🧹',
                'children' => [
                    ['code' => 'family.household.cleaning', 'emoji' => '🧼'],
                    ['code' => 'family.household.repairs', 'emoji' => '🛠️'],
                    ['code' => 'family.household.furniture_appliances', 'emoji' => '🛋️'],
                    ['code' => 'family.household.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'transport',
        'type' => 'expense',
        'emoji' => '🚗',
        'children' => [
            [
                'code' => 'transport.car',
                'emoji' => '🚘',
                'children' => [
                    ['code' => 'transport.car.fuel', 'emoji' => '⛽'],
                    ['code' => 'transport.car.maintenance', 'emoji' => '🔧'],
                    ['code' => 'transport.car.parking_tolls', 'emoji' => '🅿️'],
                    ['code' => 'transport.car.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'transport.getting_around',
                'emoji' => '🚌',
                'children' => [
                    ['code' => 'transport.getting_around.taxi', 'emoji' => '🚕'],
                    ['code' => 'transport.getting_around.public_transport', 'emoji' => '🚆'],
                    ['code' => 'transport.getting_around.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'personal',
        'type' => 'expense',
        'emoji' => '🧍',
        'children' => [
            [
                'code' => 'personal.health',
                'emoji' => '🩺',
                'children' => [
                    ['code' => 'personal.health.pharmacy', 'emoji' => '💊'],
                    ['code' => 'personal.health.doctor', 'emoji' => '👨‍⚕️'],
                    ['code' => 'personal.health.dentist', 'emoji' => '🦷'],
                    ['code' => 'personal.health.lab_tests', 'emoji' => '🔬'],
                    ['code' => 'personal.health.optician', 'emoji' => '👓'],
                    ['code' => 'personal.health.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'personal.care',
                'emoji' => '💇',
                'children' => [
                    ['code' => 'personal.care.toiletries', 'emoji' => '🧴'],
                    ['code' => 'personal.care.hairdresser_beauty', 'emoji' => '💅'],
                    ['code' => 'personal.care.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'personal.clothing',
                'emoji' => '👕',
                'children' => [
                    ['code' => 'personal.clothing.clothes', 'emoji' => '👗'],
                    ['code' => 'personal.clothing.shoes', 'emoji' => '👟'],
                    ['code' => 'personal.clothing.children_clothes', 'emoji' => '🧒'],
                    ['code' => 'personal.clothing.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'entertainment',
        'type' => 'expense',
        'emoji' => '🎭',
        'children' => [
            [
                'code' => 'entertainment.outings',
                'emoji' => '🎟️',
                'children' => [
                    ['code' => 'entertainment.outings.cinema_shows', 'emoji' => '🎬'],
                    ['code' => 'entertainment.outings.events', 'emoji' => '🎡'],
                    ['code' => 'entertainment.outings.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'entertainment.sport_hobbies',
                'emoji' => '🏋️',
                'children' => [
                    ['code' => 'entertainment.sport_hobbies.gym_sport', 'emoji' => '🏃'],
                    ['code' => 'entertainment.sport_hobbies.games_hobbies', 'emoji' => '🎮'],
                    ['code' => 'entertainment.sport_hobbies.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'entertainment.subscriptions',
                'emoji' => '📺',
                'children' => [
                    ['code' => 'entertainment.subscriptions.streaming', 'emoji' => '🎞️'],
                    ['code' => 'entertainment.subscriptions.apps_services', 'emoji' => '📲'],
                    ['code' => 'entertainment.subscriptions.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'entertainment.travel',
                'emoji' => '✈️',
                'children' => [
                    ['code' => 'entertainment.travel.tickets', 'emoji' => '🎫'],
                    ['code' => 'entertainment.travel.accommodation', 'emoji' => '🏨'],
                    ['code' => 'entertainment.travel.activities', 'emoji' => '🗺️'],
                    ['code' => 'entertainment.travel.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'celebrations',
        'type' => 'expense',
        'emoji' => '🎉',
        'children' => [
            [
                'code' => 'celebrations.muslim_holidays',
                'emoji' => '🌙',
                'children' => [
                    ['code' => 'celebrations.muslim_holidays.ramadan', 'emoji' => null],
                    ['code' => 'celebrations.muslim_holidays.eid_al_fitr', 'emoji' => null],
                    ['code' => 'celebrations.muslim_holidays.eid_al_adha', 'emoji' => null],
                    ['code' => 'celebrations.muslim_holidays.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'celebrations.christian_holidays',
                'emoji' => '🎄',
                'children' => [
                    ['code' => 'celebrations.christian_holidays.christmas', 'emoji' => null],
                    ['code' => 'celebrations.christian_holidays.easter', 'emoji' => null],
                    ['code' => 'celebrations.christian_holidays.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'celebrations.jewish_holidays',
                'emoji' => '🕎',
                'children' => [
                    ['code' => 'celebrations.jewish_holidays.hanukkah', 'emoji' => null],
                    ['code' => 'celebrations.jewish_holidays.passover', 'emoji' => null],
                    ['code' => 'celebrations.jewish_holidays.rosh_hashanah', 'emoji' => null],
                    ['code' => 'celebrations.jewish_holidays.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'celebrations.family_events',
                'emoji' => '🎂',
                'children' => [
                    ['code' => 'celebrations.family_events.birthdays', 'emoji' => '🎂'],
                    ['code' => 'celebrations.family_events.weddings', 'emoji' => '💍'],
                    ['code' => 'celebrations.family_events.new_year', 'emoji' => '🎆'],
                    ['code' => 'celebrations.family_events.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'celebrations.gifts_giving',
                'emoji' => '🎁',
                'children' => [
                    ['code' => 'celebrations.gifts_giving.gifts_given', 'emoji' => '🎁'],
                    ['code' => 'celebrations.gifts_giving.charity', 'emoji' => '🤲'],
                    ['code' => 'celebrations.gifts_giving.family_help', 'emoji' => '👪'],
                    ['code' => 'celebrations.gifts_giving.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'finance',
        'type' => 'expense',
        'emoji' => '🧾',
        'children' => [
            [
                'code' => 'finance.banking',
                'emoji' => '🏦',
                'children' => [
                    ['code' => 'finance.banking.bank_fees', 'emoji' => '💳'],
                    ['code' => 'finance.banking.loan_repayment', 'emoji' => '💸'],
                    ['code' => 'finance.banking.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'finance.insurance',
                'emoji' => '🛡️',
                'children' => [
                    ['code' => 'finance.insurance.home', 'emoji' => '🏠'],
                    ['code' => 'finance.insurance.car', 'emoji' => '🚗'],
                    ['code' => 'finance.insurance.health', 'emoji' => '🩺'],
                    ['code' => 'finance.insurance.life', 'emoji' => '❤️'],
                    ['code' => 'finance.insurance.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'finance.taxes_paperwork',
                'emoji' => '📄',
                'children' => [
                    ['code' => 'finance.taxes_paperwork.taxes', 'emoji' => '🏛️'],
                    ['code' => 'finance.taxes_paperwork.official_fees', 'emoji' => '📑'],
                    ['code' => 'finance.taxes_paperwork.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'misc',
        'type' => 'expense',
        'emoji' => '🧺',
        'children' => [
            [
                'code' => 'misc.tobacco_alcohol',
                'emoji' => '🚬',
                'children' => [
                    ['code' => 'misc.tobacco_alcohol.cigarettes', 'emoji' => '🚬'],
                    ['code' => 'misc.tobacco_alcohol.vaping', 'emoji' => '💨'],
                    ['code' => 'misc.tobacco_alcohol.shisha', 'emoji' => '🫖'],
                    ['code' => 'misc.tobacco_alcohol.alcohol', 'emoji' => '🍷'],
                    ['code' => 'misc.tobacco_alcohol.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'misc.other',
                'emoji' => '❓',
                'children' => [
                    ['code' => 'misc.other.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'income',
        'type' => 'income',
        'emoji' => '💼',
        'children' => [
            [
                'code' => 'income.work',
                'emoji' => '💼',
                'children' => [
                    ['code' => 'income.work.salary', 'emoji' => '💰'],
                    ['code' => 'income.work.bonus', 'emoji' => '🎯'],
                    ['code' => 'income.work.freelance', 'emoji' => '🧑‍💻'],
                    ['code' => 'income.work.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'income.assets',
                'emoji' => '📈',
                'children' => [
                    ['code' => 'income.assets.rental_income', 'emoji' => '🏘️'],
                    ['code' => 'income.assets.investment_income', 'emoji' => '📊'],
                    ['code' => 'income.assets.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'income.other_income',
                'emoji' => '🤲',
                'children' => [
                    ['code' => 'income.other_income.gifts_received', 'emoji' => '🎁'],
                    ['code' => 'income.other_income.benefits', 'emoji' => '🏛️'],
                    ['code' => 'income.other_income.refunds', 'emoji' => '↩️'],
                    ['code' => 'income.other_income.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
    [
        'code' => 'savings',
        'type' => 'saving',
        'emoji' => '🪙',
        'children' => [
            [
                'code' => 'savings.reserves',
                'emoji' => '🛟',
                'children' => [
                    ['code' => 'savings.reserves.emergency_fund', 'emoji' => '🆘'],
                    ['code' => 'savings.reserves.savings_goal', 'emoji' => '🎯'],
                    ['code' => 'savings.reserves.other', 'emoji' => '📝'],
                ],
            ],
            [
                'code' => 'savings.long_term',
                'emoji' => '📈',
                'children' => [
                    ['code' => 'savings.long_term.investments', 'emoji' => '📊'],
                    ['code' => 'savings.long_term.retirement', 'emoji' => '🧓'],
                    ['code' => 'savings.long_term.other', 'emoji' => '📝'],
                ],
            ],
        ],
    ],
];
