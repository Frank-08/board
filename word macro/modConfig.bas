Attribute VB_Name = "modConfig"
Option Explicit

' --- Board API configuration ---
Public Const API_BASE_URL As String = "https://togetherincouncil.com/api/"
Public Const API_KEY As String = "PASTE-YOUR-API-KEY-HERE"

' --- Field names returned by GET agenda.php ---
' Check these against the real response (or database/schema.sql) once
' the API key endpoint exists, and adjust if they don't match.
Public Const FIELD_ITEM_ID As String = "id"
Public Const FIELD_ITEM_NUMBER As String = "item_number"
Public Const FIELD_ITEM_TITLE As String = "title"

' --- Bookmark prefix used to tag agenda-item headings in the minutes doc ---
' Word bookmark names must start with a letter - do not change the
' underscore to anything else without checking Word's naming rules.
Public Const BOOKMARK_PREFIX As String = "AI_"
