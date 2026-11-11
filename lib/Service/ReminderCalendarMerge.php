<?php
declare(strict_types=1);
namespace OCA\CoBudget\Service;

/** Field-level three-way merge. A missing baseline requires an explicit choice for differences. */
final class ReminderCalendarMerge {
 public const FIELDS = ['title', 'description', 'remind_at'];
 public static function fields(array $row): array {
  return ['title'=>(string)$row['title'], 'description'=>(string)($row['description'] ?? ''), 'remind_at'=>(int)$row['remind_at']];
 }
 public static function merge(?array $base, array $local, array $remote): array {
  $merged=$local; $conflicts=[];
  foreach (self::FIELDS as $field) {
   if ($local[$field] === $remote[$field]) continue;
   if ($base !== null && $remote[$field] === $base[$field]) continue;
   if ($base !== null && $local[$field] === $base[$field]) $merged[$field]=$remote[$field];
   else $conflicts[$field]=['local'=>$local[$field], 'calendar'=>$remote[$field]];
  }
  return ['merged'=>$merged, 'fields'=>$conflicts];
 }
}
