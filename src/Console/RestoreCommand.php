<?php

namespace Exceedone\Exment\Console;

use Illuminate\Console\Command;
use Exceedone\Exment\Enums\BackupTarget;
use Exceedone\Exment\Services\BackupRestore;
use Exceedone\Exment\Services\Installer\EnvTrait;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Define;
use \File;

class RestoreCommand extends Command
{
    use CommandTrait, EnvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exment:restore {file?} {--tmp=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore database definition, table data, files in selected folder';

    protected $restore;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->initExmentCommand();

        $this->restore = new BackupRestore\Restore;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->restore->initBackupRestore();

            $file = $this->getFile();
            $tmp = boolval($this->option("tmp"));

            $result = $this->restore->execute($file, $tmp);
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->restore->diskService()->deleteTmpDirectory();
        }
    }

    protected function getFile(){
        $file = $this->argument("file");
            
        if(!is_nullorempty($file)){
            return $file;
        }

        // get backup file list
        $list = $this->restore->list();

        if(count($list) == 0){
            $this->info('Backup file not found.');
        }

        $file = $this->choice('Please choice backup file.', collect($list)->pluck('file_name')->toArray());

        return $file;
    }
}
