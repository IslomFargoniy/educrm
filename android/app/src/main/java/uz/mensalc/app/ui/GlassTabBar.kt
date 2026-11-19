package uz.mensalc.app.ui

import android.os.Build
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.BlurEffect
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.TileMode
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.layer.GraphicsLayer
import androidx.compose.ui.graphics.layer.drawLayer
import androidx.compose.ui.graphics.rememberGraphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.layout.positionInRoot
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/** Space the floating tab bar covers at the bottom; scrollable screens add it as bottom padding. */
val LocalBottomBarInset = compositionLocalOf { 0.dp }

data class GlassTab(val label: String, val icon: ImageVector, val selected: Boolean, val onClick: () -> Unit)

/** Recorded screen content that the glass bar blurs behind itself. */
class Backdrop(val layer: GraphicsLayer) {
    var origin by mutableStateOf(Offset.Zero)
}

@Composable
fun rememberBackdrop(): Backdrop {
    val layer = rememberGraphicsLayer()
    return remember(layer) { Backdrop(layer) }
}

/** Records whatever this modifier draws so a [GlassTabBar] can show it blurred. */
fun Modifier.backdropSource(backdrop: Backdrop): Modifier = this
    .onGloballyPositioned { backdrop.origin = it.positionInRoot() }
    .drawWithContent {
        backdrop.layer.record { this@drawWithContent.drawContent() }
        drawLayer(backdrop.layer)
    }

/**
 * Floating "liquid glass" tab bar: a capsule that blurs the content behind it,
 * with a sliding highlight under the selected tab.
 */
@Composable
fun GlassTabBar(tabs: List<GlassTab>, backdrop: Backdrop, dark: Boolean, modifier: Modifier = Modifier) {
    val shape = RoundedCornerShape(percent = 50)
    var barOrigin by remember { mutableStateOf(Offset.Zero) }
    val canBlur = Build.VERSION.SDK_INT >= Build.VERSION_CODES.S
    // Neutral iOS-style glass: graphite in dark mode, frosted white in light mode.
    val tint = when {
        dark && canBlur -> Color(0xFF2C2C2E).copy(alpha = 0.72f)
        dark -> Color(0xFF2C2C2E).copy(alpha = 0.96f)
        canBlur -> Color(0xFFF9F9F9).copy(alpha = 0.70f)
        else -> Color(0xFFF9F9F9).copy(alpha = 0.96f)
    }
    val rim = Brush.verticalGradient(
        if (dark) listOf(Color.White.copy(alpha = 0.22f), Color.White.copy(alpha = 0.05f)) else listOf(Color.White, Color.Black.copy(alpha = 0.06f)),
    )
    val sheen = Brush.verticalGradient(
        listOf(Color.White.copy(alpha = if (dark) 0.06f else 0.35f), Color.Transparent),
    )

    Box(
        modifier
            .fillMaxWidth()
            .height(GlassBarHeight)
            .shadow(20.dp, shape, ambientColor = Color.Black.copy(alpha = 0.18f), spotColor = Color.Black.copy(alpha = 0.22f))
            .clip(shape)
            .onGloballyPositioned { barOrigin = it.positionInRoot() },
    ) {
        // Blurred copy of the screen behind the bar.
        if (canBlur) {
            Box(
                Modifier.matchParentSize()
                    .graphicsLayer { renderEffect = BlurEffect(24.dp.toPx(), 24.dp.toPx(), TileMode.Clamp) }
                    .drawBehind {
                        val shift = backdrop.origin - barOrigin
                        translate(shift.x, shift.y) { drawLayer(backdrop.layer) }
                    },
            )
        }
        Box(Modifier.matchParentSize().background(tint).background(sheen).border(1.dp, rim, shape))

        BoxWithConstraints(Modifier.fillMaxSize().padding(4.dp)) {
            val itemWidth = maxWidth / tabs.size.coerceAtLeast(1)
            val selectedIndex = tabs.indexOfFirst { it.selected }.coerceAtLeast(0)
            val pillOffset by animateDpAsState(
                itemWidth * selectedIndex,
                spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessMediumLow),
                label = "pill",
            )
            SelectionPill(Modifier.offset(x = pillOffset).width(itemWidth).fillMaxHeight(), dark)

            Row(Modifier.fillMaxSize(), horizontalArrangement = Arrangement.SpaceEvenly) {
                tabs.forEach { tab -> GlassTabItem(tab, itemWidth, dark) }
            }
        }
    }
}

@Composable
private fun SelectionPill(modifier: Modifier, dark: Boolean) {
    val shape = RoundedCornerShape(percent = 50)
    Box(
        modifier
            .clip(shape)
            .background(if (dark) Color.Black.copy(alpha = 0.32f) else Color.Black.copy(alpha = 0.06f))
            .border(0.5.dp, Color.White.copy(alpha = if (dark) 0.08f else 0.6f), shape),
    )
}

@Composable
private fun GlassTabItem(tab: GlassTab, width: Dp, dark: Boolean) {
    val color by animateColorAsState(
        when {
            tab.selected && dark -> IosBlueDark
            tab.selected -> IosBlue
            dark -> Color.White
            else -> Color(0xFF1C1C1E)
        },
        label = "tabColor",
    )
    Column(
        Modifier.width(width).fillMaxHeight()
            .clip(RoundedCornerShape(percent = 50))
            .selectable(
                selected = tab.selected,
                onClick = tab.onClick,
                role = Role.Tab,
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
            ),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(tab.icon, contentDescription = null, tint = color, modifier = Modifier.size(26.dp))
        Text(tab.label, color = color, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, modifier = Modifier.padding(top = 1.dp))
    }
}

/** Height of the floating bar. */
val GlassBarHeight = 62.dp

/** iOS system blue used for the selected tab. */
private val IosBlue = Color(0xFF007AFF)
private val IosBlueDark = Color(0xFF0A84FF)
