<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $employees = [
            [
                'Staff', null, null, 'Dave Anthony', 'Alico', 'Vergara', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mary Gail', 'Flores', 'Baybayan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Sabina', 'Legaspi', 'Mendoza', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Izrael Percy', 'Union', 'Sajise', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Luisito', 'Pineda', 'Salvador', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Raquel', 'Ruales', 'Samson', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rusty', 'Lopez', 'Cabangon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Teresita', 'Pagunsan', 'Abrico', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rodolfo', 'Nisola', 'David', 'Jr.', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gaspar', 'Valois', 'Ambita', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Renato', 'Inlajusta', 'Torregosa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Reymark', 'Raoet', 'Dela Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marie Joy', 'Bermas', 'Sillon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rutchel', 'Andaya', 'Abanto', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Chiqui', 'Uno', 'Esteban', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rosemarie', 'Roa', 'Clet', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ramon', null, 'Trinidad', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Victoria', 'Rangas', 'Santos', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lea Anne', 'Iranga', 'Cacanindin', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gem Marie', 'null', 'Loro', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Elmer', 'Duene', 'Marfiga', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'John Benedict', 'Obillo', 'Fernando', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Uziel', 'Avellaneda', 'Rutaquio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ma. Cristina', 'De leon.', 'Pacubas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Raymond Rae', 'Regala', 'Castañeda', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Angelica', 'Magat', 'Ibarra-Nisce', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Isabel', 'Palma', 'Rivera', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jhoana Rose', 'Militante', 'Santisteban', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Margarett Grace', 'Esperat', 'Soriano', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Benson', "Ng", 'Manda', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Arlene', 'Naval', 'Eguillon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rodolfo', 'Garcia', 'Ocera', 'Jr.', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Juner', 'Entize', 'Reyes', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Charity', 'Balabbo', 'Cuntapay', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Glenn', 'Aquino', 'Vivas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Judith', 'Caridad', 'Baldemor', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Clodoaido', 'Magpantay', 'Caringal', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rashida Cawiya', 'Catipon', 'Guiling-Decena', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Malaika', 'Laurente', 'Salido-Ecalnir', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Joanne Raquel', 'Roque', 'Teodoro', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mario Victor', 'Manoto', 'Villardo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marites', 'Rabe', 'Razonable', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rashida Sakia', 'Catipon', 'Guiling', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Judith Fatima', 'Estabillo', 'Garcia', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rey Christian', 'Ampayo', 'Barbosa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Robert', 'Bernardo', 'Belleza', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marichu', 'Abela', 'Brillantes', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Leah', 'Faderon', 'Fajutagana', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Diana Marie', 'Torres', 'Tolentino', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Kathrina Rosario', 'Bautista', 'Agustin', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cristela', 'Veñegas', 'Veñegas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Hedelita', 'Redondo', 'Cabotage', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Albert', 'Mendoza', ' De Jesus', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Brian Rainier', 'Talion', 'Herradura', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jose Leonardo', 'Ramos', 'Gabriel', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jennifer', 'Manlutac', 'Cheng', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Hannyline', 'Abas', 'Funtilar', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Sharon Grace', 'Vicente', 'Meneses', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Anna Liza', 'Campos', 'Salita', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lia Kathleen', 'Nuarin', 'Abisana', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jenniefer Kristine', 'Ico', 'Balingit', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Kristine Lizette', 'Villar', 'Caringal', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ana Margarita', 'Catolico', 'Pineda', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jocelyn', 'Quinto', 'Gacasan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Krisha', 'Llerena', 'Baluyut', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Hannah Grace', 'Butel', 'Baquiran-Lat', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lolita', 'Santos', 'De Rueda', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jay-Pee', 'Ramos', 'Viloria', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Christina Joyce', 'Cabiluna', 'Bastida', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Louisse Kimberlyn', 'Papa', 'Taganile', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Nathalie Mae', 'Vega', 'Martin', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Carlo Jay', 'Borja', 'Ragas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ronello', 'Bernal', 'Sulayao', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Alona', 'Argenio', 'Medallo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'La Niña', 'Tagalogon', 'Ubal', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Rosela', 'Buenaventura', 'Espinar', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Angeline', 'De Leon', 'Nicandro', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Willy', 'Hermo', 'De Vera', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Tisha Jane', 'De Guzman', 'Benitez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Dexter John', 'Tumaneng', 'Catiwa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Arnold Benedict', 'Pascual', 'Yambao', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Keithlyn', 'Sarmiento', 'Roxas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marissa Anne', 'De Jesus', 'Abellar', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Naomi Noreen', 'Olivares', 'Buenafe', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Monica', 'Abendan', 'Correa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Neil', 'Gaddi', 'Cabbo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Patrick John', 'Biglang-Awa', 'Del Rosario', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Luisa', 'Balajadia', 'Evangelista', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Eugene Timothy', 'Madamba', 'Wingsing', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Regine Rose', 'Requiestas', 'Logrosa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Remedios', 'Ramirez', 'Romero', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Genelyn', 'Balbiran', 'Aquino', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Angel', 'Altiche', 'Nielo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gundelino', 'Dela Cruz', 'Sanchez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rosen Lord', 'Hernandez', 'Cabrera', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Catherine', 'Sandoy', 'Cabotaje', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'David', 'Tuazon', 'Boque', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jennelyn', 'Sunio', 'Silarde', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Manuel', 'Gerna', 'Payad', 'III', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gladys', 'H.', 'Guevarra', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jhesel', 'Quezada', 'Dela Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jasmine', 'San Jose', 'Escosa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Darwin', 'Olaya', 'Leonardo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lloyd', 'Gatmaitan', 'Doremon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marijane', 'Abella', 'Lagno', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Joana Marie', 'Callueng', 'Macayaon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Richard', 'Ines', 'Valino', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Albert Ryan', 'Javellana', 'Chua', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Kim', 'Silvestre', 'Tabilin', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ailyn', 'Dengal', 'Sabroso', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jade', 'Labay', 'Salvador', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Louren Angelica', 'Concepcion', 'Agunod', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gremond', 'Tumanon', 'Rulloda', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Novy-Ann', 'Navera', 'Abrigo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Glenda', 'Jose', 'Alison', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ritchie', 'De Gracia.', 'Kilapio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ainy', 'Torcita', 'Pendon-Niogan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Josephine', 'Musenabre', 'Balao-Baclagan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Charyll Maevelyn', 'Tribaco', 'Baylon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mark Alvin', 'Onal', 'Catedral', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Joey', 'Asperin', 'Cordero', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Janet', 'Mabuan', 'Harion', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Loraine', 'Sarmiento', 'Mendoza', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Kristina', 'Parochilin', 'Tatad', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ma. Janah Clarissa', 'Pecho', 'Jimenez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lovelyn', 'Salo', 'Vilog', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Estella', 'Capili', 'Cenia', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jonalyn', 'Rivera', 'Cattian', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Berna-Lor Nica', 'Velarde', 'Cabillar', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Wendy Sairah', 'Santos', 'Mamansag', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marlon', 'Tesorero', 'Bulatao', 'Jr.', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Christine Joy', 'Iguin', 'Tango', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jonalyn', 'San Jose', 'Ligon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rommel', 'Dela Cruz', 'Siblang', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jeffry', 'Sales', 'Dela Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jenilyn', 'Lopez', 'Guardian', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Luzviminda', 'Dayag', 'Pascual', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mariliza', 'Paraggua', 'Balabbo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Carmen', 'Celestial', 'Cellona', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cristy Jane', 'Cueto', 'Bugarin', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Sheila', 'Patlingrao', 'Adorable', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Genara Valerie', 'Aydinan', 'Bumad-Ong', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mary Jane', 'Paragas', 'Roldan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jason', 'Torres', 'Delos Santos', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Imelda', 'Visda', 'Didulo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Gwen', 'Publico', 'Gamet', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Britannica', 'Abalo', 'Lingling', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Aiza', 'Tumaliuan', 'Mallari', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Paulo', 'Soledad', 'Mamiloto', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Irene', 'Calingacion', 'Manso', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maribel', 'Deocampo', 'Patlingrao', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Angeline', 'Buenaflor', 'Arellano', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'John Kevin', 'Barcellano', 'Yalong', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Hershey', 'Buison', 'Gresola', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Melbert', 'Recepcion', 'Evangelista', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Allan', 'Caraos', 'Belo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cindy', 'Lapez', 'Candaroma', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Renz Joharra', 'Abalos', 'Santos', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Connie Ann', 'Hagoriles', 'Silva', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ranee', 'Gutierrez', 'Sangalang', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Ruth', 'See', 'Galut', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Crispina', 'Ocrisma', 'Bulanadi', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Shiela Mae', 'Ausan', 'Hayan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Nikko', 'Tacapan', 'Laurico', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Charmaigne', 'Gabriel', 'Agtang', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Herminio', 'Espiritu', 'Sarmiento', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Angelica', 'Basan', 'Remitera', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Purita', 'David', 'Dantes', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Princess Coleen', 'Book', 'Baloloy', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Stephanie', 'Aquino', 'Sombillo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Virgie', 'Edejer', 'Edloy', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marcelino', 'Janoras', 'Palitic', 'III', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ma. Vicarl', 'Taguba', 'Estacio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Theresa', 'Figueroa', 'Cezar', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Joanna Marie', 'Mencias', 'Fernandez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Kimberly Beth Ponce', 'De Leon', 'Kampton', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Kristina', 'Vallecruz', 'Macario', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Armida', 'Evangelista', 'Estrada', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Michael Benedict', 'Peregrino', 'Villamor', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cyrwins', 'Amedo', 'Tamon', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Philippe', 'Yu', 'Wang', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Lady Jane', 'Galo', 'Gaffud', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Carl Christian', 'Nebres', 'Hersano', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mark Anthony', 'Nicolas', 'Aggabao', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Noreen Eunice', 'Cabanding', 'Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Christine', 'Villena', 'Ragas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Julina', 'Nungay', 'De Asis', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Anita', 'Jaime', 'Cecillano', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Glory Ann', 'Alarcon', 'Montes', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Claire Shyla', 'Vinluan', 'Rio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Virgilio', 'Peñaroyo', 'Tolentino', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ralph', 'Jerez', 'Acosta', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ma. Kate Lyn Anne', 'Libao', 'Salvador', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jonel', 'Garcia', 'Balabbo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Clilene', 'Cuenco', 'Perez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maria Maycel', null, 'Banado', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jan Bert', 'Isidoro', 'Doquenia', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Art', 'Evangelista', 'Jimenez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jimmy', 'Sales', 'Dela Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Nancy', 'Columbres', 'Ydia', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ma. Lourdes Teresa', 'Espinosa', 'Jawili', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Vanessa Mae', 'Pagba', 'Estidola', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Donnary', 'A.', 'Gavan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Joshua Anthony', 'Luzande', 'Palaad', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Michelle Jaen', null, 'Vajio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Annalyka', 'Andrada', 'Carullo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jan', 'Racel Belches', 'Montes', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marc Napoleon', 'Aquino', 'Quiaoit', 'III', null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Victor', 'Velarde', 'Abapo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Felipe', 'Dellosa', 'De Jesus', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mark Anthony', 'Reoma', 'Campo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Rj', 'Balaobao', 'Beatingo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mark', null, 'Sanipa', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Jose Rolly', 'Verano', 'Flores', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Carlito', 'Leynes', 'Maliwanag', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ronwaldo', 'Domingo', 'Emerenciana', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Dennis', 'Gomez', 'Gutierrez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mercy', 'Montuya', 'Cruz', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Mark Lester', 'Saliba', 'Tesorero', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Darwin', 'Cañega', 'Canonce', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cherry', 'Nilla', 'Borja', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ruel', 'Velasco', 'Subaran', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Corazon', 'Cerdan', 'Rodrigo', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ronilo', 'Mogol', 'Jose', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Cecille', 'Jaen', 'Vajio', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Maureen', 'Watiwat', 'Villadolid', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Edelina', 'Ballola', 'Sabulao', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Luzvi May', 'Buscaino', 'Quiaoit', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Marion May', 'Meneses', 'Cervera', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Ginalyn', 'Rubia', 'Oxales', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Reijen Ayessa', 'Cabo', 'Manansala', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Nathaniel', 'Vargas', 'Galut', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Margie', 'Molito', 'Lucañas', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Pia Angelina', 'C.', 'Tan', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
            [
                'Staff', null, null, 'Bradford Antonio', 'C.', 'Martinez', null, null, 'Plantilla', 'active', null, null, false, false, null,
            ],
        ];

        foreach ($employees as $emp) {
            $departmentIds = $emp[1] !== null ? json_encode([$emp[1]]) : null;
            $rolePosition  = json_encode([$emp[0]]);

            DB::table('employees')->insert([
                'role_position'        => $rolePosition,
                'division_id'          => null,
                'department_ids'       => $departmentIds,
                'prefix'               => $emp[2],
                'first_name'           => $emp[3],
                'middle_name'          => $emp[4],
                'last_name'            => $emp[5],
                'suffix'               => $emp[6],
                'position_designation' => null,
                'title'                => $emp[7],
                'employment_type'      => $emp[8],
                'employment_status'    => $emp[9],
                'avatar_url'           => $emp[10],
                'border_color'         => $emp[11],
                'aligned'              => $emp[12],
                'expanded'             => $emp[13],
                'notes'                => $emp[14],
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }
}