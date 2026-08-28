package org.togetherincouncil.mobile.data.remote

import com.squareup.moshi.Json
import com.squareup.moshi.JsonAdapter
import com.squareup.moshi.JsonDataException
import com.squareup.moshi.JsonReader
import com.squareup.moshi.JsonWriter
import com.squareup.moshi.Moshi
import com.squareup.moshi.Types
import java.lang.reflect.Type

/**
 * Handles every enum DTO field in one place, replacing per-type
 * EnumJsonAdapter.create(...).withUnknownFallback(...) registrations.
 *
 * That per-type approach didn't reliably null-check before Moshi's stock
 * EnumJsonAdapter tried to read a string — confirmed on-device: a real JSON
 * null for a nullable enum field (agenda_items[].report_type) reached
 * EnumJsonAdapter.fromJson() directly and crashed with "Expected a string
 * but was NULL", even after wrapping the registered adapter in .nullSafe().
 * This factory checks for a null token as the very first thing it does, in
 * the same fromJson() call Moshi actually invokes, so there's no separate
 * wrapper object whose application could go missing.
 *
 * Also folds in the UNKNOWN-fallback behavior every enum in this codebase
 * already relies on: an unrecognized string degrades to the enum's UNKNOWN
 * constant instead of throwing, so a server-side value this build doesn't
 * know about yet doesn't blank the whole screen's JSON parse.
 */
object LenientEnumAdapterFactory : JsonAdapter.Factory {
    override fun create(type: Type, annotations: MutableSet<out Annotation>, moshi: Moshi): JsonAdapter<*>? {
        val rawType = Types.getRawType(type)
        if (!rawType.isEnum || annotations.isNotEmpty()) return null
        @Suppress("UNCHECKED_CAST")
        return LenientEnumAdapter(rawType as Class<out Enum<*>>)
    }
}

private class LenientEnumAdapter(private val enumClass: Class<out Enum<*>>) : JsonAdapter<Enum<*>>() {

    private val constants: Array<out Enum<*>> = enumClass.enumConstants
        ?: throw IllegalArgumentException("${enumClass.name} has no enum constants")

    // Match on @Json(name=...) when present (e.g. "Formal Majority"), falling back to the
    // Kotlin constant name itself for anything left unannotated.
    private val jsonNameToConstant: Map<String, Enum<*>> = constants.associateBy { constant ->
        enumClass.getField(constant.name).getAnnotation(Json::class.java)?.name ?: constant.name
    }

    private val unknownConstant: Enum<*>? = constants.firstOrNull { it.name == "UNKNOWN" }

    override fun fromJson(reader: JsonReader): Enum<*>? {
        if (reader.peek() == JsonReader.Token.NULL) {
            return reader.nextNull()
        }
        val value = reader.nextString()
        return jsonNameToConstant[value]
            ?: unknownConstant
            ?: throw JsonDataException(
                "Expected one of ${jsonNameToConstant.keys} but was $value at path ${reader.path}"
            )
    }

    override fun toJson(writer: JsonWriter, value: Enum<*>?) {
        if (value == null) {
            writer.nullValue()
            return
        }
        val jsonName = jsonNameToConstant.entries.firstOrNull { it.value == value }?.key ?: value.name
        writer.value(jsonName)
    }
}
