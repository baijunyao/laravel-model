<?php

namespace Baijunyao\LaravelModel\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * 禁止被批量赋值的字段
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * 添加数据
     *
     * @param      $data   插入的内容
     * @param bool $flash  是否需要成功或者失败的提示
     *
     * @return bool|mixed
     */
    public function storeData($data, $flash = true)
    {
        // 处理data为空的情况
        if (empty($data)) {
            flash_error('无需要添加的数据', $flash);
            return false;
        }
        //添加数据
        $result = $this->create($data);
        if ($result) {

            flash_success('添加成功', $flash);
            return $result->id;
        }else{
            flash_error('添加失败', $flash);
            return false;
        }
    }

    /**
     * 修改数据
     *
     * @param      $map   修改的where
     * @param      $data  修改的数据
     * @param bool $flash 是否需要成功或者失败的提示
     *
     * @return bool
     */
    public function updateData($map, $data, $flash = true)
    {
        if (empty($map)) {
            flash_error('条件为空', $flash);
            return false;
        }

        if (empty($data)) {
            flash_error('修改的数据为空', $flash);
            return false;
        }

        $model = $this
            ->whereMap($map)
            ->withTrashed()
            ->get();

        // 可能有查不到数据的情况
        if ($model->isEmpty()) {
            flash_error('无需要添加的数据', $flash);
            return false;
        }
        foreach ($model as $k => $v) {
            $result = $v->forceFill($data)->save();
        }
        if ($result) {
            flash_success('修改成功', $flash);
            return $result;
        }else{
            flash_error('修改失败', $flash);
            return false;
        }
    }

    /**
     * 删除数据
     *
     * @param      $map   删除的where
     * @param      $data  删除的数据
     * @param bool $flash 是否需要成功或者失败的提示
     *
     * @return bool
     */
    public function destroyData($map, $flash = true)
    {
        if (empty($map)) {
            flash_error('条件为空', $flash);
            return false;
        }

        // 软删除
        $result=$this
            ->whereMap($map)
            ->delete();
        if ($result) {
            flash_success('删除成功', $flash);
            return $result;
        }else{
            flash_error('删除失败', $flash);
            return false;
        }
    }

    /**
     * 恢复数据
     *
     * @param      $map   恢复的数据where
     * @param bool $flash 是否需要成功或者失败的提示
     *
     * @return bool恢复数据
     */
    public function restoreData($map, $flash = true)
    {
        if (empty($map)) {
            flash_error('条件为空', $flash);
            return false;
        }
        // 恢复
        $result=$this
            ->whereMap($map)
            ->restore();
        if ($result) {
            flash_success('恢复成功', $flash);
            return $result;
        }else{
            flash_error('恢复失败', $flash);
            return false;
        }
    }

    /**
     * 彻底删除
     *
     * @param      $map   彻底删除的数据where
     * @param bool $flash 是否需要成功或者失败的提示
     *
     * @return bool
     */
    public function forceDeleteData($map, $flash = true)
    {
        if (empty($map)) {
            flash_error('条件为空', $flash);
            return false;
        }
        // 彻底删除
        $result=$this
            ->whereMap($map)
            ->forceDelete();
        if ($result) {
            flash_success('彻底删除成功', $flash);
            return $result;
        }else{
            flash_error('彻底删除失败', $flash);
            return false;
        }
    }

    /**
     * 使用作用域扩展 Builder 链式操作
     *
     * 示例:
     * $map = [
     *     'id' => ['in', [1,2,3]],
     *     'category_id' => ['<>', 9],
     *     'tag_id' => 10
     * ]
     *
     * @param $query
     * @param $map
     * @return mixed
     */
    public function scopeWhereMap($query, array $map)
    {
        // 如果是空直接返回
        if (empty($map)) {
            return $query;
        }
        // 判断关系是 and 还是 or
        $where = 'where';
        if (isset($map['_logic'])) {
            $logic = strtolower($map['_logic']);
            $where = $logic == 'or' ? 'orWhere' : 'where';
            unset($map['_logic']);
        }
        // 判断各种方法
        foreach ($map as $k => $v) {
            if (is_array($v)) {
                $sign = strtolower($v[0]);
                switch ($sign) {
                    case 'in':
                        $query->{$where.'In'}($k, $v[1]);
                        break;
                    case 'notin':
                        $query->{$where.'NotIn'}($k, $v[1]);
                        break;
                    case 'between':
                        $query->{$where.'Between'}($k, $v[1]);
                        break;
                    case 'notbetween':
                        $query->{$where.'NotBetween'}($k, $v[1]);
                        break;
                    case 'null':
                        $query->{$where.'Null'}($k);
                        break;
                    case 'notnull':
                        $query->{$where.'NotNull'}($k);
                        break;
                    case '=':
                    case '>':
                    case '<':
                    case '<>':
                    case 'like':
                        $query->{$where}($k, $sign, $v[1]);
                        break;
                }
            } else {
                $query->$where($k, $v);
            }
        }
        return $query;
    }

    /**
     * 批量更新的方法
     * 示例参数
     * $multipleData = [
     *    [
     *        'name' => 'name 1' ,
     *        'date' => 'date 1'
     *     ],
     *     [
     *        'name' => 'name 2' ,
     *        'date' => 'date 2'
     *      ]
     *   ]
     *
     * @param array $multipleData
     * @param bool  $flash         是否需要成功或者失败的提示
     * @return bool|int
     */
    function updateBatch($multipleData = [], $flash = true)
    {
        if (empty($multipleData)) {
            return false;
        }
        // 获取表名
        $tableName = config('database.connections.mysql.prefix').$this->getTable();
        $updateColumn = array_keys($multipleData[0]);
        $referenceColumn = $updateColumn[0];
        unset($updateColumn[0]);
        $whereIn = "";
        // 组合sql语句
        $sql = "UPDATE ".$tableName." SET ";
        foreach ( $updateColumn as $uColumn ) {
            $sql .=  $uColumn." = CASE ";
            foreach( $multipleData as $data ) {
                $sql .= "WHEN ".$referenceColumn." = '".$data[$referenceColumn]."' THEN '".$data[$uColumn]."' ";
            }
            $sql .= "ELSE ".$uColumn." END, ";
        }
        foreach( $multipleData as $data ) {
            $whereIn .= "'".$data[$referenceColumn]."', ";
        }
        $sql = rtrim($sql, ", ")." WHERE ".$referenceColumn." IN (".  rtrim($whereIn, ', ').")";
        // 更新
        $result = DB::update(DB::raw($sql));
        if ($result) {
            flash_success('操作成功', $flash);
        } else {
            flash_error('操作失败', $flash);
        }
        return $result;
    }
}
