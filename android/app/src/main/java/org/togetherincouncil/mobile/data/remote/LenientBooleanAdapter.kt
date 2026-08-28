package org.togetherincouncil.mobile.data.remote

import com.squareup.moshi.JsonAdapter
import com.squareup.moshi.JsonReader
import com.squareup.moshi.JsonWriter

/**
 * PHP's PDO/json_encode() serializes MySQL BOOLEAN (i.e. TINYINT(1)) columns
 * as a JSON number (0/1), not a JSON true/false literal — confirmed against
 * the real backend's meetings.php ("Expected a boolean but was NUMBER at
 * path $.quorum_met" crashing the stock Moshi boolean adapter). This affects
 * every Boolean DTO field sourced from a DB column (quorum_met, is_starred,
 * casting_vote_used, requires_leave, returned, ...), so it's registered once
 * here for both Boolean and Boolean? rather than patched per field.
 */
object LenientBooleanAdapter : JsonAdapter<Boolean>() {
    override fun fromJson(reader: JsonReader): Boolean = when (reader.peek()) {
        JsonReader.Token.BOOLEAN -> reader.nextBoolean()
        JsonReader.Token.NUMBER -> reader.nextDouble() != 0.0
        JsonReader.Token.STRING -> {
            val value = reader.nextString()
            value == "1" || value.equals("true", ignoreCase = true) || value.equals("yes", ignoreCase = true)
        }
        else -> reader.nextBoolean() // surfaces Moshi's own clear error for a genuinely unexpected token
    }

    override fun toJson(writer: JsonWriter, value: Boolean?) {
        writer.value(value)
    }
}
