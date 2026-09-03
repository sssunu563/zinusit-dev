<?php

namespace App\Services;

use App\Models\Stb;
use App\Models\Peminjaman;

/**
 * Standardized Asset Note/Catatan Formatter
 * 
 * Formats all asset assignment and activity notes to use consistent format:
 * "STB-ZGI-2609-0012 | Doc ID: ZGI-2609-0012 | Item: Mouse | SN: asdadadasd | Assign: Muliana | Catatan: adsadasd | Ref: NB-FA-P-MAY23-007"
 */
class AssetNoteFormatterService
{
    /**
     * Format complete asset assignment note with all details
     * 
     * @param mixed $document STB or Peminjaman model
     * @param string $itemName Name of the asset/item
     * @param string|null $serialNo Serial number of the item
     * @param string|null $assignedTo Person/recipient name
     * @param string|null $catatan Additional notes/remarks
     * @param string|null $reference Reference identifier (e.g., employee ID, location code)
     * @return string Formatted note string
     */
    public static function formatAssignmentNote(
        mixed $document,
        string $itemName,
        ?string $serialNo = null,
        ?string $assignedTo = null,
        ?string $catatan = null,
        ?string $reference = null
    ): string {
        $docId = self::getDocumentId($document);
        $docIdFormatted = self::formatDocumentId($document);
        
        $parts = [
            $docId, // STB-ZGI-2609-0012
            "Doc ID: {$docIdFormatted}", // Doc ID: ZGI-2609-0012
        ];
        
        // Add Item name
        if (!empty($itemName)) {
            $parts[] = "Item: {$itemName}";
        }
        
        // Add Serial No
        if (!empty($serialNo)) {
            $parts[] = "SN: {$serialNo}";
        }
        
        // Add Assign to (recipient/assignee)
        if (!empty($assignedTo)) {
            $parts[] = "Assign: {$assignedTo}";
        }
        
        // Add Catatan (remarks/notes)
        if (!empty($catatan)) {
            $parts[] = "Catatan: {$catatan}";
        }
        
        // Add Reference (employee ID, location code, etc)
        if (!empty($reference)) {
            $parts[] = "Ref: {$reference}";
        }
        
        return implode(' | ', $parts);
    }

    /**
     * Format simple assignment note (minimal details)
     * Used for quick activity logging
     * 
     * @param mixed $document STB or Peminjaman model
     * @param string|null $action Action description (e.g., "assigned", "returned")
     * @param string|null $recipient Recipient name
     * @return string Formatted note string
     */
    public static function formatSimpleNote(
        mixed $document,
        ?string $action = null,
        ?string $recipient = null
    ): string {
        $docId = self::getDocumentId($document);
        
        $note = $docId;
        
        if (!empty($action)) {
            $note .= " | {$action}";
        }
        
        if (!empty($recipient)) {
            $note .= " ke {$recipient}";
        }
        
        return $note;
    }

    /**
     * Format condition-specific note (for hardware)
     * 
     * @param mixed $document STB or Peminjaman model
     * @param string|null $condition Item condition (Good, Broken, Missing)
     * @param string|null $catatan Additional remarks
     * @return string Formatted note string
     */
    public static function formatConditionNote(
        mixed $document,
        ?string $condition = null,
        ?string $catatan = null
    ): string {
        $docId = self::getDocumentId($document);
        
        $parts = [$docId];
        
        if (!empty($condition)) {
            $parts[] = "Kondisi: " . strtoupper($condition);
        }
        
        if (!empty($catatan)) {
            $parts[] = "Catatan: {$catatan}";
        }
        
        return implode(' | ', $parts);
    }

    /**
     * Parse existing note and enrich it with new format
     * Used to upgrade existing notes to new format
     * 
     * @param mixed $document STB or Peminjaman model
     * @param string $oldNote Existing note text
     * @param array $additionalData Additional fields to include
     * @return string Formatted note string
     */
    public static function enrichExistingNote(
        mixed $document,
        string $oldNote,
        array $additionalData = []
    ): string {
        // If old note already contains pipe separator, it's likely already formatted
        if (str_contains($oldNote, '|')) {
            return $oldNote;
        }
        
        // Extract useful information from old note if possible
        $itemName = $additionalData['item_name'] ?? null;
        $serialNo = $additionalData['serial_no'] ?? null;
        $assignedTo = $additionalData['assigned_to'] ?? null;
        $reference = $additionalData['reference'] ?? null;
        
        // Use old note as catatan if no additional data
        $catatan = $oldNote;
        
        return self::formatAssignmentNote(
            $document,
            $itemName,
            $serialNo,
            $assignedTo,
            $catatan,
            $reference
        );
    }

    /**
     * Extract fields from a formatted note string
     * 
     * @param string $note Formatted note string
     * @return array Extracted fields
     */
    public static function parseNote(string $note): array {
        $fields = [
            'stb_id' => null,
            'doc_id' => null,
            'item' => null,
            'sn' => null,
            'assign' => null,
            'catatan' => null,
            'ref' => null,
        ];
        
        // Split by pipe separator
        $parts = explode('|', $note);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Parse STB ID (e.g., "STB-ZGI-2609-0012")
            if (str_starts_with($part, 'STB-')) {
                $fields['stb_id'] = $part;
            }
            
            // Parse Doc ID
            if (str_starts_with($part, 'Doc ID:')) {
                $fields['doc_id'] = trim(substr($part, 7));
            }
            
            // Parse Item
            if (str_starts_with($part, 'Item:')) {
                $fields['item'] = trim(substr($part, 5));
            }
            
            // Parse Serial Number
            if (str_starts_with($part, 'SN:')) {
                $fields['sn'] = trim(substr($part, 3));
            }
            
            // Parse Assign
            if (str_starts_with($part, 'Assign:')) {
                $fields['assign'] = trim(substr($part, 7));
            }
            
            // Parse Catatan
            if (str_starts_with($part, 'Catatan:')) {
                $fields['catatan'] = trim(substr($part, 8));
            }
            
            // Parse Reference
            if (str_starts_with($part, 'Ref:')) {
                $fields['ref'] = trim(substr($part, 4));
            }
        }
        
        return $fields;
    }

    /**
     * Get document ID with prefix (e.g., "STB-ZGI-2609-0012")
     */
    private static function getDocumentId(mixed $document): string
    {
        if ($document instanceof Stb) {
            return 'STB-' . self::formatDocumentId($document);
        }
        
        if ($document instanceof Peminjaman) {
            return 'PINJAM-' . self::formatDocumentId($document);
        }
        
        // Fallback
        return 'DOC-' . ($document->id ?? 'unknown');
    }

    /**
     * Format document ID without prefix (e.g., "ZGI-2609-0012")
     */
    private static function formatDocumentId(mixed $document): string
    {
        // Get created_at date
        $createdAt = $document->created_at ?? now();
        $yearMonth = self::getYearMonthCode($createdAt);
        
        if ($yearMonth === '') {
            return (string) $document->id;
        }
        
        // Extract location code (3 letters from location_name)
        $locationCode = self::extractLocationCode($document->location_name ?? '');
        $paddedId = sprintf('%04d', $document->id);
        
        if ($locationCode) {
            return sprintf('%s-%s-%s', $locationCode, $yearMonth, $paddedId);
        }
        
        return sprintf('%s-%s', $yearMonth, $paddedId);
    }

    /**
     * Get year-month code (e.g., "2609" for Sept 26 / 09/26)
     */
    private static function getYearMonthCode($date): string
    {
        $dateObj = $date instanceof \DateTime ? $date : \DateTime::createFromFormat('Y-m-d H:i:s', (string) $date);
        
        if (!$dateObj) {
            return '';
        }
        
        // Format: MMDD (month + day)
        return $dateObj->format('mdy')[4] . $dateObj->format('mdy')[5] . 
               $dateObj->format('mdy')[2] . $dateObj->format('mdy')[3];
    }

    /**
     * Extract 3-letter location code from location name
     * e.g., "ZGI BGR F1" → "ZGI"
     */
    private static function extractLocationCode(?string $locationName): string
    {
        if (empty($locationName) || $locationName === '-') {
            return '';
        }
        
        $firstWord = explode(' ', trim($locationName))[0];
        return strtoupper(substr($firstWord, 0, 3));
    }
}
