<?php

namespace Elseyyid\LaravelJsonLocationsManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Filesystem\Filesystem;
use Elseyyid\LaravelJsonLocationsManager\Models\Strings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elseyyid:location:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Package';

    /**
     * Create a new command instance.
     *
     * @return void
    */
    public function __construct(Filesystem $filesystem)
    {
    parent::__construct();
    $this->files = $filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
    */
    public function handle()
    {
        if (!Schema::hasTable('strings')) {
            $this->createSchema();
        } else {
            $this->createSchema("fresh");
        }
        
        $this->line('');
        $this->info('Database Created!');

        $this->importJsonStrings();
        $this->info('Json strings imported!');

        $this->line('Package installed!');
        $this->info('');
    }

    public function importArraysStrings()
    {
        $files = $this->files->directories(base_path('lang'));
        $all = [];
        $langs = [];
        $codes = [];
        foreach ($files as $key => $value) {
            $lang = Arr::last(explode('/',$value));
            if ($lang !== 'vendor' ) {
                array_push($langs,$lang);
                $a = $this->files->allFiles($value);
                $array = [];
                foreach ($a as $k => $v) {
                    $m = explode('.php',Arr::last(explode('/',$v)))[0];
                    array_push($array,Arr::dot([$m => include($v)]));
                }

                list($keys, $values) = Arr::divide(Arr::collapse($array));

                array_push($codes,$keys);
                array_push($all,[$lang => Arr::collapse($array)]);
            }
        }
        $languages = Arr::collapse($all);
        $codigos = collect(Arr::collapse($codes))->unique();

        $this->isInTable($langs);

        $collect = collect();
        foreach ($codigos as $key => $codigo) {
            $fila = [];
            foreach ($languages as $key => $arr) {
                if (Arr::has($arr, $codigo)) {
                    array_push($fila,[$key => Arr::get($arr, $codigo)]);
                }
            }
            $collect->push(Arr::collapse($fila));
        }
        $this->fill($collect);
    }

    public function importJsonStrings()
    {
        $json_files = $this->files->files(base_path('lang'));
        $all = [];
        $langs = [];
        $codes = [];
        foreach ($json_files as $key => $json_file) {
            if (str_contains($json_file, '.json')) {
                $lang = Arr::first(explode('.json', basename($json_file)));
                array_push($langs,$lang);

                $array = json_decode($this->files->get($json_file));

                $keys = collect($array)->keys();

                array_push($codes,$keys);

                array_push($all,[$lang => $array ]);
            }
        }
        $languages = Arr::collapse($all);
        $codigos = collect(Arr::collapse($codes))->unique();
        $this->isInTable($langs);
        $collect = collect();
        foreach ($codigos as $key => $codigo) {
            $fila = [];
            array_push($fila,['en' => $codigo]);
            foreach ($languages as $key => $arr) {
                if( $key == 'en' ){
                    continue;
                }
                $arr = (array) $arr;
                if (Arr::has($arr, $codigo)) {
                    array_push($fila, [$key => $arr[$codigo]]);
                }
            }
            $collect->push(Arr::collapse($fila));
        }
        $this->fill($collect);
    }

    public function fill($collect)
    {
        foreach ($collect as $key => $value) {
            if (!Arr::has($value,'en')) {
                $array = collect($value);
                $array->prepend(Arr::first($value),'en');

                $string = Strings::where('en',$array['en'])->first();
                if (!isset($string->code)) {
                    Strings::create($array->toArray());
                }
            } else {
                $string = Strings::where('en',$value['en'])->first();
                if (!isset($string->code)) {
                    Strings::create($value);
                }
            }
        }
    }

    public function createSchema($mode = null)
    {
        if ($mode === 'fresh') {
            $this->backupTable();
            Schema::dropIfExists('strings');
        }
        Schema::create('strings', function (Blueprint $table) {
            $table->increments('code')->unsigned();
            $table->text('en')->unique();
            $table->text('ar')->nullable();
            $table->text('da')->nullable();
            $table->text('de')->nullable();
            $table->text('el')->nullable();
            $table->text('es')->nullable();
            $table->text('fr')->nullable();
            $table->text('id')->nullable();
            $table->text('it')->nullable();
            $table->text('nl')->nullable();
            $table->text('pt_BR')->nullable();
            $table->text('sv')->nullable();
            $table->text('th')->nullable();
            $table->timestamps();
        });
    }

    public function backupTable()
    {
        $backupFileName = 'backup_strings_' . now()->format('Y_m_d_His') . '.sql';
        $backupPath = storage_path('backups/');
    
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
    
        Artisan::call('db:dump', [
            '--database' => 'locations',
            '--table' => 'strings',
            '--path' => $backupPath,
            '--filename' => $backupFileName,
        ]);
    
        $this->info("Backup created: {$backupPath}{$backupFileName}");
        $this->info('');
    }

    public function isInTable($langs)
    {
        $fields = \DB::getSchemaBuilder()->getColumnListing('strings');
        foreach ($langs as $key => $value) {
            if (! in_array( $value, $fields )) {
                Schema::table('strings', function (Blueprint $table) use($value){
                    $table->text($value)->nullable();
                });
            }
        }
    }

}
