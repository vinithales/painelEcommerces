<?php
namespace App\Services\PlatformApi;
use App\Models\Store;
use App\Models\AuditEvent;
class OrdersApiService {
 public function __construct(private readonly PlatformApiAdapterFactory $factory) {}
 public function list(Store $store,array $filters,int $page,int $perPage): array {
  return ApiAudit::call($store,'list','order',null,$filters,fn()=>$this->factory->make($store)->listOrders($filters,max(1,$page),min(100,max(1,$perPage))));
 }
 public function get(Store $store,string $id): array {return ApiAudit::call($store,'get','order',$id,[],fn()=>$this->factory->make($store)->getOrder($id));}
 public function update(Store $store,string $id,array $attributes): array {
  $result=ApiAudit::call($store,'update','order',$id,$attributes,fn()=>$this->factory->make($store)->updateOrder($id,$attributes));
  AuditEvent::record($store->id,'order','update',['id'=>$id,'changes'=>$attributes]);
  return $result;
 }
}
