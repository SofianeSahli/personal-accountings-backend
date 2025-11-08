<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];

        // ----- Income categories -----
        $incomeParentId = Str::uuid();
        $categories[] = [
            'id' => $incomeParentId,
            'name' => 'categories.name.income',
            'description' => 'categories.description.income',
            'parent_id' => null,
            'is_income' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $incomeSubcategories = [
            'salary' => 'Salary or wages',
            'freelance' => 'Freelance work',
            'investments' => 'Investments income',
            'rental' => 'Rental properties',
            'gifts' => 'Monetary gifts',
            'business' => 'Business income',
            'royalties' => 'Royalties from creative work',
            'other' => 'Other income sources',
        ];

        foreach ($incomeSubcategories as $key => $desc) {
            $categories[] = [
                'id' => Str::uuid(),
                'name' => "categories.name.income.{$key}",
                'description' => "categories.description.income.{$key}",
                'parent_id' => $incomeParentId,
                'is_income' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ----- Expense categories -----
        $expenses = [
            'housing' => [
                'description' => 'Housing related expenses',
                'sub' => [
                    'rent' => 'Monthly rent',
                    'mortgage' => 'Mortgage payments',
                    'utilities' => 'Electricity, water, gas',
                    'maintenance' => 'Repairs and maintenance',
                    'property_tax' => 'Property taxes',
                    'insurance' => 'Home insurance',
                ],
            ],
            'transportation' => [
                'description' => 'Transportation expenses',
                'sub' => [
                    'fuel' => 'Fuel for vehicle',
                    'public_transport' => 'Bus, subway, train',
                    'car_maintenance' => 'Repairs and servicing',
                    'insurance' => 'Car insurance',
                    'taxi' => 'Taxi or ride-hailing',
                ],
            ],
            'food' => [
                'description' => 'Food and dining expenses',
                'sub' => [
                    'groceries' => 'Supermarket groceries',
                    'dining_out' => 'Restaurants and cafes',
                    'coffee' => 'Coffee and drinks',
                    'snacks' => 'Snacks and small purchases',
                ],
            ],
            'health' => [
                'description' => 'Health related expenses',
                'sub' => [
                    'medical' => 'Medical bills',
                    'pharmacy' => 'Pharmacy purchases',
                    'insurance' => 'Health insurance',
                    'fitness' => 'Gym and fitness',
                ],
            ],
            'education' => [
                'description' => 'Education expenses',
                'sub' => [
                    'tuition' => 'Tuition fees',
                    'books' => 'Books and materials',
                    'courses' => 'Online/offline courses',
                    'supplies' => 'Stationery and school supplies',
                ],
            ],
            'personal_care' => [
                'description' => 'Personal care and grooming',
                'sub' => [
                    'clothing' => 'Clothes and shoes',
                    'beauty' => 'Beauty products',
                    'hair' => 'Haircuts and salon',
                    'spa' => 'Spa and wellness',
                ],
            ],
            'entertainment' => [
                'description' => 'Entertainment and hobbies',
                'sub' => [
                    'movies' => 'Movies and theaters',
                    'streaming' => 'Netflix, Spotify, etc.',
                    'games' => 'Video games',
                    'events' => 'Concerts, sports, events',
                ],
            ],
            'debt' => [
                'description' => 'Debt and loans',
                'sub' => [
                    'credit_card' => 'Credit card payments',
                    'loans' => 'Personal loans',
                    'other' => 'Other debts',
                ],
            ],
            'savings' => [
                'description' => 'Savings and investments',
                'sub' => [
                    'emergency_fund' => 'Emergency fund',
                    'retirement' => 'Retirement savings',
                    'stocks' => 'Stock investments',
                    'crypto' => 'Cryptocurrency',
                ],
            ],
            'misc' => [
                'description' => 'Miscellaneous expenses',
                'sub' => [
                    'gifts' => 'Gifts for others',
                    'charity' => 'Donations and charity',
                    'other' => 'Other miscellaneous',
                ],
            ],
        ];

        foreach ($expenses as $parentKey => $data) {
            $parentId = Str::uuid();
            $categories[] = [
                'id' => $parentId,
                'name' => "categories.name.expenses.{$parentKey}",
                'description' => "categories.description.expenses.{$parentKey}",
                'parent_id' => null,
                'is_income' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($data['sub'] as $subKey => $subDesc) {
                $categories[] = [
                    'id' => Str::uuid(),
                    'name' => "categories.name.expenses.{$parentKey}.{$subKey}",
                    'description' => "categories.description.expenses.{$parentKey}.{$subKey}",
                    'parent_id' => $parentId,
                    'is_income' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert all categories
        DB::table('categories')->insert($categories);

        $this->command->info('Categories seeded successfully!');
    }
}
