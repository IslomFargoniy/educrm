package uz.mensalc.app.ui

import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import uz.mensalc.app.R

/**
 * Amount field: digits only, shown grouped by thousands ("1 803 000 so'm").
 * [digits] holds the plain number as text.
 */
@Composable
fun MoneyField(digits: String, onDigitsChange: (String) -> Unit, label: String, modifier: Modifier = Modifier) {
    OutlinedTextField(
        value = digits,
        onValueChange = { input -> onDigitsChange(input.filter(Char::isDigit).trimStart('0').take(12)) },
        label = { Text(label) },
        suffix = { Text(stringResource(R.string.sum)) },
        singleLine = true,
        visualTransformation = ThousandsVisualTransformation,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        modifier = modifier,
    )
}

/** "1803000" → "1 803 000", with a caret mapping that skips the spaces. */
object ThousandsVisualTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val digits = text.text
        val n = digits.length
        val out = StringBuilder()
        // toTransformed[o] = position in the grouped text for original offset o.
        val toTransformed = IntArray(n + 1)

        digits.forEachIndexed { i, c ->
            if (i > 0 && (n - i) % 3 == 0) out.append(' ')
            toTransformed[i] = out.length
            out.append(c)
        }
        toTransformed[n] = out.length
        val grouped = out.toString()

        val mapping = object : OffsetMapping {
            override fun originalToTransformed(offset: Int): Int = toTransformed[offset.coerceIn(0, n)]

            // Number of digits before the caret in the grouped text.
            override fun transformedToOriginal(offset: Int): Int =
                grouped.substring(0, offset.coerceIn(0, grouped.length)).count { it != ' ' }
        }

        return TransformedText(AnnotatedString(grouped), mapping)
    }
}
