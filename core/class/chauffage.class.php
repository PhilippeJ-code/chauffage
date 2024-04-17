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
    // On passe en On
    //
    public function on()
    {
        $this->getCmd(null, 'status')->event(__('On', __FILE__));
        $this->actionsConsignesOn();
    }

    // On passe en Off
    //
    public function off()
    {
        $this->getCmd(null, 'status')->event(__('Off', __FILE__));
        $this->actionsConsignesOff();
    }

    // On exécute les actions de consignes on
    //
    public function actionsConsignesOn()
    {
        if ($this->getConfiguration('csg_on_conf')) {
            foreach ($this->getConfiguration('csg_on_conf') as $action) {
                try {
                    $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                    if (!is_object($cmd)) {
                        continue;
                    }
                    $options = array();
                    if (isset($action['options'])) {
                        $options = $action['options'];
                    }
                    scenarioExpression::createAndExec('action', $action['cmd'], $options);
                } catch (Exception $e) {
                    log::add('chauffage', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
                }
            }
        }
    }

    // On éxécute les actions consignes off
    //
    public function actionsConsignesOff()
    {
        if ($this->getConfiguration('csg_off_conf')) {
            foreach ($this->getConfiguration('csg_off_conf') as $action) {
                try {
                    $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                    if (!is_object($cmd)) {
                        continue;
                    }
                    $options = array();
                    if (isset($action['options'])) {
                        $options = $action['options'];
                    }
                    scenarioExpression::createAndExec('action', $action['cmd'], $options);
                } catch (Exception $e) {
                    log::add('chauffage', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
                }
            }
        }
    }

    // Sur événement changement de la température intérieure
    //
    public static function onTemperature($_options)
    {
        $chauffage = chauffage::byId($_options['chauffage_id']);
        if (!is_object($chauffage)) {
            return;
        }
        // Gestion de la température
        //
        $chauffage->temperature();
    }

    // Gestion de la température
    //
    public function temperature()
    {
        // Mémo de la température
        //
        $this->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($this->getConfiguration('temperature_interieure')));

        $temperature = $this->getCmd(null, 'temperature')->execCmd();
        if (!is_numeric($temperature)) {
            return;
        }

        $consigne = $this->getCmd(null, 'consigne')->execCmd();
        if (!is_numeric($consigne)) {
            return;
        }

        $consigne_min = $consigne - $this->getConfiguration('hysteresis_min', 1);
        $consigne_max = $consigne + $this->getConfiguration('hysteresis_max', 1);

        if ($temperature <= $consigne_min) {
            $this->chauffe();
        } elseif ($temperature >= $consigne_max) {
            $this->pasDeChauffe();
            $elapsed = $this->getCache('elapsed', -1);
            if ($elapsed != -1) {
                $this->setCache('elapsed', -1);
                $elapsed = microtime(true) - $elapsed;
                  
                $diff = $temperature - $this->getCache('temperature', $temperature);
                if ($diff >= 3) {
                    $secondsPerDegree = $elapsed / $diff;
                    log::add('chauffage', 'info', 'Seconds Per Degree : ' . $secondsPerDegree);
                    $this->getCmd(null, 'secondsPerDegree')->event($secondsPerDegree);
                }
            }
        }
    }

    // On chauffe
    //
    public function chauffe()
    {
        if ($this->getCmd(null, 'mode')->execCmd() != 'Chauffe') {
            $this->getCmd(null, 'mode')->event(__('Chauffe', __FILE__));
            $this->actionsChauffage();
            $this->setCache('retries', $this->getConfiguration('nombre_essais', 1));
            $this->setCache('elapsed', microtime(true));
            $this->setCache('temperature', $this->getCmd(null, 'temperature')->execCmd());
        }
    }

    // On exécute les actions chauffage
    //
    public function actionsChauffage()
    {
        if ($this->getConfiguration('chf_oui_conf')) {
            foreach ($this->getConfiguration('chf_oui_conf') as $action) {
                try {
                    $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                    if (!is_object($cmd)) {
                        continue;
                    }
                    $options = array();
                    if (isset($action['options'])) {
                        $options = $action['options'];
                    }
                    scenarioExpression::createAndExec('action', $action['cmd'], $options);
                } catch (Exception $e) {
                    log::add('chauffage', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
                }
            }
        }
    }

    // On ne chauffe plus
    //
    public function pasDeChauffe()
    {
        if ($this->getCmd(null, 'mode')->execCmd() != 'Stoppé') {
            $this->getCmd(null, 'mode')->event(__('Stoppé', __FILE__));
            $this->actionsPasDeChauffage();
            $this->setCache('retries', $this->getConfiguration('nombre_essais', 1));
        }
    }

    // On n'exécute les actions on ne chauffe plus
    //
    public function actionsPasDeChauffage()
    {
        if ($this->getConfiguration('chf_non_conf')) {
            foreach ($this->getConfiguration('chf_non_conf') as $action) {
                try {
                    $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                    if (!is_object($cmd)) {
                        continue;
                    }
                    $options = array();
                    if (isset($action['options'])) {
                        $options = $action['options'];
                    }
                    scenarioExpression::createAndExec('action', $action['cmd'], $options);
                } catch (Exception $e) {
                    log::add('chauffage', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
                }
            }
        }
    }

    public function getNextState()
    {

        try {
            $plugin = plugin::byId('calendar');
            if (!is_object($plugin) || $plugin->isActive() != 1) {
                return '';
            }
        } catch (Exception $ex) {
            return '';
        }
        if (!class_exists('calendar_event')) {
            return '';
        }

        $mode = $this->getCmd(null, 'status_on');
        $next = null;
        $position = null;
        $events = calendar_event::searchByCmd($mode->getId());
        if (is_array($events) && count($events) > 0) {
            foreach ($events as $event) {
                $calendar = $event->getEqLogic();
                $stateCalendar = $calendar->getCmd(null, 'state');
                if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
                    continue;
                }
                foreach ($event->getCmd_param('start') as $action) {
                    if ($action['cmd'] == '#' . $mode->getId() . '#') {
                        $position = 'start';
                    }
                }
                foreach ($event->getCmd_param('end') as $action) {
                    if ($action['cmd'] == '#' . $mode->getId() . '#') {
                        if ($position == 'start') {
                            $position = null;
                        } else {
                            $position = 'end';
                        }
                    }
                }
                $nextOccurence = $event->nextOccurrence($position, true);
                if ($nextOccurence['date'] != '' && ($next == null || (strtotime($next['date']) > strtotime($nextOccurence['date']) && strtotime($nextOccurence['date']) > (strtotime('now') + 120)))) {
                    $next = array(
                        'date' => $nextOccurence['date'],
                        'event' => $event,
                        'calendar_id' => $calendar->getId(),
                        'cmd' => $mode->getId(),
                        'type' => 'mode',
                    );
                }
            }
        }
        return $next;
    }

    public static function cron()
    {
        // Pour chacun des équipements
        //
        foreach (chauffage::byType('chauffage', true) as $chauffage) {

            if ($chauffage->getIsEnable() == 1) {

                $nextState = $chauffage->getNextState();
                if ($nextState != '') {
                    $chauffage->getCmd(null, 'nextOnDate')->event($nextState['date']);
                }

                $temp = jeedom::evaluateExpression($chauffage->getConfiguration('temperature_exterieure'));
                if (!is_numeric($temp)) {
                    $temp = 99;
                } else {
                    $temp = round($temp, 1);
                }

                $chauffage->getCmd(null, 'extTemperature')->event($temp);

                $retries = $chauffage->getCache('retries', 0);

                if ($retries > 0) {

                    $retries--;
                    $chauffage->setCache('retries', $retries);

                    switch ($chauffage->getCmd(null, 'mode')->execCmd()) {
                        case __('Chauffe', __FILE__):
                            $chauffage->actionsChauffage();
                            break;
                        case __('Stoppé', __FILE__):
                            $chauffage->actionsPasDeChauffage();
                            break;
                    }
                }
            }
        }
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

        // Consigne
        //
        $consigne = $this->getCmd(null, 'consigne');
        if (!is_object($consigne)) {
            $consigne = new chauffageCmd();
            $consigne->setUnite('°C');
            $consigne->setName(__('Consigne', __FILE__));
            $consigne->setIsVisible(1);
            $consigne->setIsHistorized(0);
        }
        $consigne->setEqLogic_id($this->getId());
        $consigne->setType('info');
        $consigne->setSubType('numeric');
        $consigne->setLogicalId('consigne');
        $consigne->setConfiguration('minValue', $this->getConfiguration('consigne_min'));
        $consigne->setConfiguration('maxValue', $this->getConfiguration('consigne_max'));
        $consigne->setOrder(1);
        $consigne->save();

        // Thermostat
        //
        $thermostat = $this->getCmd(null, 'thermostat');
        if (!is_object($thermostat)) {
            $thermostat = new chauffageCmd();
            $thermostat->setUnite('°C');
            $thermostat->setName(__('Thermostat', __FILE__));
            $thermostat->setIsVisible(1);
            $thermostat->setIsHistorized(0);
        }
        $thermostat->setEqLogic_id($this->getId());
        $thermostat->setType('action');
        $thermostat->setSubType('slider');
        $thermostat->setLogicalId('thermostat');
        $thermostat->setValue($consigne->getId());
        $thermostat->setConfiguration('minValue', $this->getConfiguration('consigne_min'));
        $thermostat->setConfiguration('maxValue', $this->getConfiguration('consigne_max'));
        $thermostat->setOrder(2);
        $thermostat->save();

        // Statut Chauffage
        //
        $status = $this->getCmd(null, 'status');
        if (!is_object($status)) {
            $status = new chauffageCmd();
            $status->setName(__('Statut', __FILE__));
            $status->setIsVisible(1);
            $status->setIsHistorized(0);
        }
        $status->setEqLogic_id($this->getId());
        $status->setLogicalId('status');
        $status->setType('info');
        $status->setSubType('string');
        $status->setOrder(3);
        $status->save();

        $lock = $this->getCmd(null, 'status_on');
        if (!is_object($lock)) {
            $lock = new chauffageCmd();
            $lock->setName('Statut On');
            $lock->setIsVisible(1);
            $lock->setIsHistorized(0);
        }
        $lock->setEqLogic_id($this->getId());
        $lock->setType('action');
        $lock->setSubType('other');
        $lock->setLogicalId('status_on');
        $lock->setValue($status->getId());
        $lock->setOrder(4);
        $lock->save();

        $unlock = $this->getCmd(null, 'status_off');
        if (!is_object($unlock)) {
            $unlock = new chauffageCmd();
            $unlock->setName('Statut Off');
            $unlock->setIsVisible(1);
            $unlock->setIsHistorized(0);
        }
        $unlock->setEqLogic_id($this->getId());
        $unlock->setType('action');
        $unlock->setSubType('other');
        $unlock->setLogicalId('status_off');
        $unlock->setValue($status->getId());
        $unlock->setOrder(5);
        $unlock->save();

        $temperature = $this->getCmd(null, 'temperature');
        if (!is_object($temperature)) {
            $temperature = new chauffageCmd();
            $temperature->setName(__('Température', __FILE__));
            $temperature->setIsVisible(1);
            $temperature->setIsHistorized(0);
        }
        $temperature->setEqLogic_id($this->getId());
        $temperature->setType('info');
        $temperature->setSubType('numeric');
        $temperature->setLogicalId('temperature');
        $temperature->setUnite('°C');
        $temperature->setOrder(6);
        $temperature->save();

        $temperature = $this->getCmd(null, 'extTemperature');
        if (!is_object($temperature)) {
            $temperature = new chauffageCmd();
            $temperature->setName(__('Température extérieure', __FILE__));
            $temperature->setIsVisible(1);
            $temperature->setIsHistorized(0);
        }
        $temperature->setEqLogic_id($this->getId());
        $temperature->setType('info');
        $temperature->setSubType('numeric');
        $temperature->setLogicalId('extTemperature');
        $temperature->setUnite('°C');
        $temperature->setOrder(7);
        $temperature->save();

        // Mode chauffage
        //
        $mode = $this->getCmd(null, 'mode');
        if (!is_object($mode)) {
            $mode = new chauffageCmd();
            $mode->setIsVisible(1);
            $mode->setName(__('Mode', __FILE__));
            $mode->setIsVisible(1);
            $mode->setIsHistorized(0);
        }
        $mode->setEqLogic_id($this->getId());
        $mode->setLogicalId('mode');
        $mode->setType('info');
        $mode->setSubType('string');
        $mode->setOrder(8);
        $mode->save();

        // Etat suivant
        //
        $nextOnState = $this->getCmd(null, 'nextOnDate');
        if (!is_object($nextOnState)) {
            $nextOnState = new chauffageCmd();
            $nextOnState->setName(__('Etat on suivant', __FILE__));
            $nextOnState->setIsVisible(1);
            $nextOnState->setIsHistorized(0);
        }
        $nextOnState->setEqLogic_id($this->getId());
        $nextOnState->setLogicalId('nextOnDate');
        $nextOnState->setType('info');
        $nextOnState->setSubType('string');
        $nextOnState->setOrder(9);
        $nextOnState->save();

        $secondsPerDegree = $this->getCmd(null, 'secondsPerDegree');
        if (!is_object($secondsPerDegree)) {
            $secondsPerDegree = new chauffageCmd();
            $secondsPerDegree->setName(__('Secondes par degré', __FILE__));
            $secondsPerDegree->setIsVisible(1);
            $secondsPerDegree->setIsHistorized(0);
        }
        $secondsPerDegree->setEqLogic_id($this->getId());
        $secondsPerDegree->setType('info');
        $secondsPerDegree->setSubType('numeric');
        $secondsPerDegree->setLogicalId('secondsPerDegree');
        $secondsPerDegree->setOrder(10);
        $secondsPerDegree->save();

        if ($this->getIsEnable() == 1) {

            // On écoute les événements qui interviennent dans la gestion du chauffage
            //
            //   La température intérieure
            //
            $listener = listener::byClassAndFunction('chauffage', 'onTemperature', array('chauffage_id' => intval($this->getId())));
            if (!is_object($listener)) {
                $listener = new listener();
            }
            $listener->setClass('chauffage');
            $listener->setFunction('onTemperature');
            $listener->setOption(array('chauffage_id' => intval($this->getId())));
            $listener->emptyEvent();
            $cmd_id = $this->getConfiguration('temperature_interieure');
            $listener->addEvent($cmd_id);
            $listener->addEvent($consigne->getId());
            $listener->save();

        } else {
            // On supprime les écoutes
            //
            $listener = listener::byClassAndFunction('chauffage', 'onTemperature', array('chauffage_id' => intval($this->getId())));
            if (is_object($listener)) {
                $listener->remove();
            }
        }
    }

    // Fonction exécutée automatiquement avant la suppression de l'équipement
    //
    public function preRemove()
    {

        // On supprime les écoutes
        //
        $listener = listener::byClassAndFunction('chauffage', 'onTemperature', array('chauffage_id' => intval($this->getId())));
        if (is_object($listener)) {
            $listener->remove();
        }

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

        //        return eqLogic::toHtml($_version);

        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);

        $obj = $this->getCmd(null, 'status');
        $replace["#statut#"] = $obj->execCmd();
        $replace["#idStatut#"] = $obj->getId();

        $obj = $this->getCmd(null, 'status_on');
        $replace["#idStatutOn#"] = $obj->getId();

        $obj = $this->getCmd(null, 'status_off');
        $replace["#idStatutOff#"] = $obj->getId();

        $obj = $this->getCmd(null, 'temperature');
        $replace["#temperature#"] = $obj->execCmd();
        $replace["#idTemperature#"] = $obj->getId();

        $obj = $this->getCmd(null, 'extTemperature');
        $replace["#temperatureExterieure#"] = $obj->execCmd();
        $replace["#idTemperatureExterieure#"] = $obj->getId();

        $obj = $this->getCmd(null, 'mode');
        $replace["#mode#"] = $obj->execCmd();
        $replace["#idMode#"] = $obj->getId();

        $obj = $this->getCmd(null, 'consigne');
        $replace["#consigne#"] = $obj->execCmd();
        $replace["#idConsigne#"] = $obj->getId();
        $replace["#minConsigne#"] = $obj->getConfiguration('minValue');
        $replace["#maxConsigne#"] = $obj->getConfiguration('maxValue');
        $replace["#stepConsigne#"] = 0.5;

        $obj = $this->getCmd(null, 'thermostat');
        $replace["#idThermostat#"] = $obj->getId();

        $obj = $this->getCmd(null, 'nextOnDate');
        $replace["#nextOnDate#"] = $obj->execCmd();
        $replace["#idNextOnDate#"] = $obj->getId();

        return template_replace($replace, getTemplate('core', $version, 'chauffage_display', 'chauffage'));
    }


}

class chauffageCmd extends cmd
{
    // Exécution d'une commande
    //
    public function execute($_options = array())
    {
        $eqLogic = $this->getEqLogic();

        if ($this->getLogicalId() == 'status_on') {
            $eqLogic->on();
        } elseif ($this->getLogicalId() == 'status_off') {
            $eqLogic->off();
        }

        if ($this->getLogicalId() == 'thermostat') {
            if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                return;
            }
            $eqLogic->getCmd(null, 'consigne')->event($_options['slider']);
        }

    }

}
