<?php
namespace App\Services\PlatformApi;
use RuntimeException;
class OrderTransitions {
 public static function validateWoo(array $current,array $changes): void {
  if(array_keys($changes)!==['status'] || !in_array($changes['status'],['pending','processing','on-hold','completed','cancelled'],true))throw new RuntimeException('Transição não suportada. Reembolso exige operação financeira específica.');
  if(in_array($current['status']??'',['refunded','cancelled','completed'],true) && $changes['status']!==$current['status'])throw new RuntimeException('Pedido finalizado não pode ser reaberto pelo painel.');
 }
 public static function magentoOperation(array $current,array $changes): string {
  if(array_keys($changes)!==['operation'] || !in_array($changes['operation'],['cancel','hold','unhold'],true))throw new RuntimeException('Magento exige uma operação cancel, hold ou unhold; state/status não são graváveis.');
  $state=$current['state']??$current['raw']['state']??'';
  $allowed=match($changes['operation']){'unhold'=>['holded'],'hold'=>['new','pending_payment','processing'],'cancel'=>['new','pending_payment','processing'],default=>[]};
  if(!in_array($state,$allowed,true))throw new RuntimeException('Operação incompatível com o state atual do Magento.');
  return $changes['operation'];
 }
}
