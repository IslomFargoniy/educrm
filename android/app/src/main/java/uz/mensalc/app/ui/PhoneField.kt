package uz.mensalc.app.ui

import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation

/**
 * Phone field: keeps only the 9 local digits and shows them as "+998 90 123 45 67".
 * [digits] is the raw local part; send "+998$digits" to the API.
 */
@Composable
fun PhoneField(digits: String, onDigitsChange: (String) -> Unit, label: String, modifier: Modifier = Modifier) {
    OutlinedTextField(
        value = digits,
        onValueChange = { onDigitsChange(phoneLocalDigits(it)) },
        label = { Text(label) },
        placeholder = { Text("90 123 45 67") },
        prefix = { Text("+998 ") },
        singleLine = true,
        visualTransformation = PhoneVisualTransformation,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next),
        modifier = modifier,
    )
}

/** Inserts spaces after the 2nd, 5th and 7th digit: "901234567" → "90 123 45 67". */
object PhoneVisualTransformation : VisualTransformation {
    private val breaks = intArrayOf(2, 5, 7)

    override fun filter(text: AnnotatedString): TransformedText {
        val out = StringBuilder()
        text.text.forEachIndexed { i, c ->
            if (i in breaks) out.append(' ')
            out.append(c)
        }

        val mapping = object : OffsetMapping {
            override fun originalToTransformed(offset: Int): Int = offset + breaks.count { offset > it }

            override fun transformedToOriginal(offset: Int): Int {
                var original = offset
                breaks.forEachIndexed { index, b -> if (offset > b + index) original-- }
                return original.coerceIn(0, text.length)
            }
        }

        return TransformedText(AnnotatedString(out.toString()), mapping)
    }
}
