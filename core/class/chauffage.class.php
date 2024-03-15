<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

  require_once __DIR__  . '/../../../../core/php/core.inc.php';

  class chauffage extends eqLogic 
  {

    public static function cron()
    {
    }


    
    // Fonction exécutée automatiquement avant la création de l'équipement 
    //
    public function preInsert() 
    {
        
    }

    // Fonction exécutée automatiquement après la création de l'équipement 
    //
    public function postInsert() 
    {
        
    }

    // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    //
    public function preUpdate() 
    {
        
    }

    // Fonction exécutée automatiquement après la mise à jour de l'équipement 
    //
    public function postUpdate() 
    {
    
    }

    // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement 
    //
    public function preSave() 
    {
        
    }

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    //
    public function postSave() 
    {
         
    }

    // Fonction exécutée automatiquement avant la suppression de l'équipement 
    //
    public function preRemove() 
    {
        
    }

    // Fonction exécutée automatiquement après la suppression de l'équipement 
    //
    public function postRemove() 
    {
        
    }

   
    // Permet de modifier l'affichage du widget (également utilisable par les commandes)
    //
    public function toHtml($_version = 'dashboard')
    {

      $replace = $this->preToHtml($_version);
      if (!is_array($replace)) 
      {
        return $replace;
      }
      $version = jeedom::versionAlias($_version);

      return template_replace($replace, getTemplate('core', $version, 'chauffage_display', 'chauffage'));
    }
    

  }
  
  class chauffageCmd extends cmd 
  {
    // Exécution d'une commande  
    //
    public function execute($_options = array()) 
    {

    }

  }
