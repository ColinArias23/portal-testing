<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlantillaItem;
use App\Models\PlantillaPosition;
use App\Models\SalaryGrade;
use App\Models\StepIncrement;

class PlantillaItemSeeder extends Seeder
{
    private array $positions = [
        ['base' => '1',   'sg' => 27, 'slots' => 1,  'title' => 'City Government Department Head III'],
        ['base' => '2',   'sg' => 26, 'slots' => 1,  'title' => 'City Government Department Head II (Medical Center Chief I) (CTI)'],
        ['base' => '3',   'sg' => 11, 'slots' => 1,  'title' => 'Computer Maintenance Technologist I'],
        ['base' => '4',   'sg' => 9,  'slots' => 1,  'title' => 'Administrative Assistant III (Secretary II)'],
        ['base' => '5',   'sg' => 25, 'slots' => 1,  'title' => 'Medical Specialist IV'],
        ['base' => '6',   'sg' => 7,  'slots' => 1,  'title' => 'Administrative Assistant I (Computer Operator I)'],
        ['base' => '7',   'sg' => 24, 'slots' => 1,  'title' => 'Medical Specialist III'],
        ['base' => '8',   'sg' => 23, 'slots' => 4,  'title' => 'Medical Specialist II'],
        ['base' => '9',   'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '10',  'sg' => 15, 'slots' => 1,  'title' => 'Psychologist II'],
        ['base' => '11',  'sg' => 14, 'slots' => 1,  'title' => 'Health Education and Promotion Officer II (CTI)'],
        ['base' => '12',  'sg' => 15, 'slots' => 4,  'title' => 'Nurse I'],
        ['base' => '13',  'sg' => 4,  'slots' => 1,  'title' => 'Nursing Attendant I'],
        ['base' => '14',  'sg' => 11, 'slots' => 1,  'title' => 'Administrative Officer II (Administrative Officer I)'],
        ['base' => '15',  'sg' => 7,  'slots' => 5,  'title' => 'Administrative Assistant I (Computer Operator I)'],
        ['base' => '16',  'sg' => 18, 'slots' => 1,  'title' => 'Nutritionist-Dietitian III'],
        ['base' => '17',  'sg' => 15, 'slots' => 1,  'title' => 'Nutritionist-Dietitian II (CTI)'],
        ['base' => '18',  'sg' => 11, 'slots' => 2,  'title' => 'Nutritionist-Dietitian I'],
        ['base' => '19',  'sg' => 5,  'slots' => 4,  'title' => 'Cook II'],
        ['base' => '20',  'sg' => 3,  'slots' => 4,  'title' => 'Administrative Aide III (CTI)'],
        ['base' => '21',  'sg' => 3,  'slots' => 2,  'title' => 'Administrative Aide III (Utility Worker II)'],
        ['base' => '22',  'sg' => 18, 'slots' => 1,  'title' => 'Pharmacist III'],
        ['base' => '23',  'sg' => 11, 'slots' => 6,  'title' => 'Pharmacist I'],
        ['base' => '24',  'sg' => 4,  'slots' => 1,  'title' => 'Administrative Aide IV (CTI)'],
        ['base' => '25',  'sg' => 3,  'slots' => 1,  'title' => 'Administrative Aide III (Utility Worker II)'],
        ['base' => '26',  'sg' => 18, 'slots' => 1,  'title' => 'Social Welfare Officer III'],
        ['base' => '27',  'sg' => 11, 'slots' => 3,  'title' => 'Social Welfare Officer I'],
        ['base' => '28',  'sg' => 8,  'slots' => 1,  'title' => 'Social Welfare Assistant'],
        ['base' => '29',  'sg' => 24, 'slots' => 1,  'title' => 'Medical Specialist III'],
        ['base' => '30',  'sg' => 23, 'slots' => 2,  'title' => 'Medical Specialist II'],
        ['base' => '31',  'sg' => 21, 'slots' => 6,  'title' => 'Medical Officer III'],
        ['base' => '32',  'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '33',  'sg' => 16, 'slots' => 10, 'title' => 'Nurse II'],
        ['base' => '34',  'sg' => 6,  'slots' => 6,  'title' => 'Nursing Attendant II'],
        ['base' => '35',  'sg' => 24, 'slots' => 5,  'title' => 'Medical Specialist III'],
        ['base' => '36',  'sg' => 23, 'slots' => 12, 'title' => 'Medical Specialist II'],
        ['base' => '37',  'sg' => 23, 'slots' => 6,  'title' => 'Medical Officer IV'],
        ['base' => '38',  'sg' => 21, 'slots' => 12, 'title' => 'Medical Officer III'],
        ['base' => '39',  'sg' => 24, 'slots' => 2,  'title' => 'Medical Specialist III'],
        ['base' => '40',  'sg' => 21, 'slots' => 4,  'title' => 'Medical Officer III'],
        ['base' => '41',  'sg' => 24, 'slots' => 1,  'title' => 'Medical Specialist III'],
        ['base' => '42',  'sg' => 6,  'slots' => 1,  'title' => 'Administrative Aide VI (Clerk III)'],
        ['base' => '43',  'sg' => 23, 'slots' => 1,  'title' => 'Medical Specialist II'],
        ['base' => '44',  'sg' => 18, 'slots' => 1,  'title' => 'Medical Technologist III'],
        ['base' => '45',  'sg' => 15, 'slots' => 3,  'title' => 'Medical Technologist II'],
        ['base' => '46',  'sg' => 16, 'slots' => 3,  'title' => 'Nurse II'],
        ['base' => '47',  'sg' => 23, 'slots' => 1,  'title' => 'Medical Specialist II'],
        ['base' => '48',  'sg' => 18, 'slots' => 1,  'title' => 'Medical Technologist III'],
        ['base' => '49',  'sg' => 15, 'slots' => 3,  'title' => 'Medical Technologist II'],
        ['base' => '50',  'sg' => 15, 'slots' => 1,  'title' => 'Chemist II'],
        ['base' => '51',  'sg' => 11, 'slots' => 4,  'title' => 'Medical Technologist I'],
        ['base' => '52',  'sg' => 8,  'slots' => 4,  'title' => 'Medical Laboratory Technician II'],
        ['base' => '53',  'sg' => 4,  'slots' => 4,  'title' => 'Laboratory Aide II'],
        ['base' => '54',  'sg' => 24, 'slots' => 1,  'title' => 'Medical Specialist III'],
        ['base' => '55',  'sg' => 23, 'slots' => 2,  'title' => 'Medical Specialist II'],
        ['base' => '56',  'sg' => 18, 'slots' => 1,  'title' => 'Radiologic Technologist III'],
        ['base' => '57',  'sg' => 15, 'slots' => 3,  'title' => 'Radiologic Technologist II'],
        ['base' => '58',  'sg' => 11, 'slots' => 3,  'title' => 'Radiologic Technologist I'],
        ['base' => '59',  'sg' => 4,  'slots' => 1,  'title' => 'Administrative Aide IV (Clerk II)'],
        ['base' => '60',  'sg' => 23, 'slots' => 1,  'title' => 'Dentist IV'],
        ['base' => '61',  'sg' => 17, 'slots' => 1,  'title' => 'Dentist II'],
        ['base' => '62',  'sg' => 4,  'slots' => 1,  'title' => 'Dental Aide'],
        ['base' => '63',  'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Information Officer III)'],
        ['base' => '64',  'sg' => 15, 'slots' => 1,  'title' => 'Statistician II'],
        ['base' => '65',  'sg' => 15, 'slots' => 1,  'title' => 'Administrative Officer IV (Information Officer II)'],
        ['base' => '66',  'sg' => 10, 'slots' => 1,  'title' => 'Administrative Officer I (CTI)'],
        ['base' => '67',  'sg' => 11, 'slots' => 1,  'title' => 'Administrative Officer II (Information Officer I)'],
        ['base' => '68',  'sg' => 8,  'slots' => 2,  'title' => 'Administrative Assistant II (Data Controller II)'],
        ['base' => '69',  'sg' => 23, 'slots' => 2,  'title' => 'Medical Specialist II (Part-Time)'],
        ['base' => '70',  'sg' => 17, 'slots' => 2,  'title' => 'Nurse III'],
        ['base' => '71',  'sg' => 15, 'slots' => 1,  'title' => 'Medical Technologist II'],
        ['base' => '72',  'sg' => 11, 'slots' => 1,  'title' => 'Midwife II'],
        ['base' => '73',  'sg' => 4,  'slots' => 1,  'title' => 'Nursing Attendant I'],
        ['base' => '74',  'sg' => 4,  'slots' => 1,  'title' => 'Administrative Aide IV (Driver II)'],
        ['base' => '75',  'sg' => 3,  'slots' => 1,  'title' => 'Administrative Aide III (Utility Worker II)'],
        ['base' => '76',  'sg' => 22, 'slots' => 1,  'title' => 'Nurse VI'],
        ['base' => '77',  'sg' => 20, 'slots' => 1,  'title' => 'Nurse V'],
        ['base' => '78',  'sg' => 19, 'slots' => 1,  'title' => 'Nurse IV'],
        ['base' => '79',  'sg' => 6,  'slots' => 1,  'title' => 'Administrative Aide VI (Clerk III)'],
        ['base' => '80',  'sg' => 17, 'slots' => 2,  'title' => 'Nurse III'],
        ['base' => '81',  'sg' => 16, 'slots' => 4,  'title' => 'Nurse II'],
        ['base' => '82',  'sg' => 15, 'slots' => 51, 'title' => 'Nurse I'],
        ['base' => '83',  'sg' => 4,  'slots' => 26, 'title' => 'Nursing Attendant I'],
        ['base' => '84',  'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '85',  'sg' => 16, 'slots' => 2,  'title' => 'Nurse II'],
        ['base' => '86',  'sg' => 15, 'slots' => 12, 'title' => 'Nurse I'],
        ['base' => '87',  'sg' => 6,  'slots' => 2,  'title' => 'Nursing Attendant II (CTI)'],
        ['base' => '88',  'sg' => 4,  'slots' => 6,  'title' => 'Nursing Attendant I'],
        ['base' => '89',  'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '90',  'sg' => 16, 'slots' => 6,  'title' => 'Nurse II'],
        ['base' => '91',  'sg' => 15, 'slots' => 5,  'title' => 'Nurse I'],
        ['base' => '92',  'sg' => 9,  'slots' => 5,  'title' => 'Midwife I'],
        ['base' => '93',  'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '94',  'sg' => 16, 'slots' => 6,  'title' => 'Nurse II'],
        ['base' => '95',  'sg' => 6,  'slots' => 2,  'title' => 'Nursing Attendant II (CTI)'],
        ['base' => '96',  'sg' => 4,  'slots' => 3,  'title' => 'Nursing Attendant I'],
        ['base' => '97',  'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '98',  'sg' => 16, 'slots' => 6,  'title' => 'Nurse II'],
        ['base' => '99',  'sg' => 6,  'slots' => 2,  'title' => 'Nursing Attendant II'],
        ['base' => '100', 'sg' => 17, 'slots' => 1,  'title' => 'Nurse III'],
        ['base' => '101', 'sg' => 16, 'slots' => 14, 'title' => 'Nurse II'],
        ['base' => '102', 'sg' => 6,  'slots' => 6,  'title' => 'Nursing Attendant II'],
        ['base' => '103', 'sg' => 14, 'slots' => 1,  'title' => 'Respiratory Therapist II'],
        ['base' => '104', 'sg' => 10, 'slots' => 3,  'title' => 'Respiratory Therapist I'],
        ['base' => '105', 'sg' => 4,  'slots' => 3,  'title' => 'Laboratory Aide II'],
        ['base' => '106', 'sg' => 16, 'slots' => 1,  'title' => 'Nurse II'],
        ['base' => '107', 'sg' => 6,  'slots' => 2,  'title' => 'Nursing Attendant II'],
        ['base' => '108', 'sg' => 4,  'slots' => 5,  'title' => 'Nursing Attendant I'],
        ['base' => '109', 'sg' => 24, 'slots' => 1,  'title' => 'Chief Administrative Officer (Administrative Officer V)'],
        ['base' => '110', 'sg' => 7,  'slots' => 1,  'title' => 'Administrative Assistant I (CTI)'],
        ['base' => '111', 'sg' => 6,  'slots' => 1,  'title' => 'Administrative Aide VI (Clerk III)'],
        ['base' => '112', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Human Resource Management Officer III)'],
        ['base' => '113', 'sg' => 15, 'slots' => 1,  'title' => 'Administrative Officer IV (Human Resource Management Officer II)'],
        ['base' => '114', 'sg' => 14, 'slots' => 1,  'title' => 'Administrative Officer III (CTI)'],
        ['base' => '115', 'sg' => 11, 'slots' => 1,  'title' => 'Administrative Officer II (Human Resource Management Officer I)'],
        ['base' => '116', 'sg' => 8,  'slots' => 2,  'title' => 'Administrative Assistant II (Human Resource Management Assistant I)'],
        ['base' => '117', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Supply Officer III)'],
        ['base' => '118', 'sg' => 14, 'slots' => 1,  'title' => 'Administrative Officer III (Supply Officer II)'],
        ['base' => '119', 'sg' => 9,  'slots' => 1,  'title' => 'Administrative Assistant III (Buyer III)'],
        ['base' => '120', 'sg' => 7,  'slots' => 2,  'title' => 'Administrative Assistant I (Computer Operator I)'],
        ['base' => '121', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Budget Officer III)'],
        ['base' => '122', 'sg' => 15, 'slots' => 1,  'title' => 'Administrative Officer IV (Budget Officer II)'],
        ['base' => '123', 'sg' => 8,  'slots' => 1,  'title' => 'Administrative Assistant II (Budgeting Assistant)'],
        ['base' => '124', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Supply Officer III)'],
        ['base' => '125', 'sg' => 8,  'slots' => 1,  'title' => 'Warehouseman II'],
        ['base' => '126', 'sg' => 7,  'slots' => 1,  'title' => 'Administrative Assistant I (Computer Operator I)'],
        ['base' => '127', 'sg' => 4,  'slots' => 3,  'title' => 'Administrative Aide IV (Storekeeper I)'],
        ['base' => '128', 'sg' => 19, 'slots' => 1,  'title' => 'Engineer III'],
        ['base' => '129', 'sg' => 16, 'slots' => 1,  'title' => 'Engineer II'],
        ['base' => '130', 'sg' => 11, 'slots' => 2,  'title' => 'Medical Equipment Technician III (CTI)'],
        ['base' => '131', 'sg' => 8,  'slots' => 1,  'title' => 'Medical Equipment Technician II'],
        ['base' => '132', 'sg' => 6,  'slots' => 2,  'title' => 'Medical Equipment Technician I'],
        ['base' => '133', 'sg' => 6,  'slots' => 2,  'title' => 'Administrative Aide VI (Electrician II)'],
        ['base' => '134', 'sg' => 5,  'slots' => 1,  'title' => 'Administrative Aide V (Plumber II)'],
        ['base' => '135', 'sg' => 5,  'slots' => 1,  'title' => 'Administrative Aide V (Carpenter II)'],
        ['base' => '136', 'sg' => 4,  'slots' => 4,  'title' => 'Administrative Aide IV (Driver II) (CTI)'],
        ['base' => '137', 'sg' => 3,  'slots' => 3,  'title' => 'Administrative Aide III (CTI)'],
        ['base' => '138', 'sg' => 6,  'slots' => 2,  'title' => 'Administrative Aide VI (Utility Foreman)'],
        ['base' => '139', 'sg' => 3,  'slots' => 2,  'title' => 'Laundry Worker II'],
        ['base' => '140', 'sg' => 2,  'slots' => 1,  'title' => 'Seamstress'],
        ['base' => '141', 'sg' => 19, 'slots' => 1,  'title' => 'Accountant III'],
        ['base' => '142', 'sg' => 16, 'slots' => 1,  'title' => 'Accountant II'],
        ['base' => '143', 'sg' => 8,  'slots' => 4,  'title' => 'Administrative Assistant II (Accounting Clerk III)'],
        ['base' => '144', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Administrative Officer III)'],
        ['base' => '145', 'sg' => 8,  'slots' => 1,  'title' => 'Administrative Assistant II (Accounting Clerk III)'],
        ['base' => '146', 'sg' => 6,  'slots' => 5,  'title' => 'Administrative Aide VI (Clerk III)'],
        ['base' => '147', 'sg' => 18, 'slots' => 1,  'title' => 'Administrative Officer V (Cashier III)'],
        ['base' => '148', 'sg' => 10, 'slots' => 5,  'title' => 'Administrative Officer I (Cashier I)'],
        ['base' => '149', 'sg' => 8,  'slots' => 1,  'title' => 'Administrative Assistant II (Cash Clerk III)'],
    ];

    public function run(): void
    {
        $sgCache   = SalaryGrade::pluck('id', 'salary_grade')->toArray();
        $stepCache = StepIncrement::where('step', 1)->pluck('id', 'salary_grade_id')->toArray();

        foreach ($this->positions as $def) {

            if (! isset($sgCache[$def['sg']])) {
                $sg = SalaryGrade::create([
                    'salary_grade'   => $def['sg'],
                    'monthly_salary' => null,
                    'annual_salary'  => null,
                ]);
                $sgCache[$def['sg']] = $sg->id;
            }

            $sgId   = $sgCache[$def['sg']];
            $stepId = $stepCache[$sgId] ?? null;

            $item = PlantillaItem::firstOrCreate(
                [
                    'base_item_number' => $def['base'],
                    'title'            => $def['title'],
                ],
                [
                    'approved_slots' => $def['slots'],
                    'description'    => null,
                ]
            );

            for ($i = 1; $i <= $def['slots']; $i++) {
                $itemNumber = $def['slots'] === 1
                    ? $def['base']
                    : $def['base'] . '-' . $i;

                PlantillaPosition::updateOrCreate(
                    ['item_number' => $itemNumber],
                    [
                        'plantilla_item_id' => $item->id,
                        'position_title'    => $def['title'],
                        'salary_grade_id'   => $sgId,
                        'step_increment_id' => $stepId,
                        'status'            => 'VACANT',
                        'requires_approval' => false,
                        'vacated_at'        => null,
                        'vacancy_notes'     => null,
                    ]
                );
            }
        }
    }
}